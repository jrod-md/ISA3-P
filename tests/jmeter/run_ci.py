"""CI-only JMeter runner: owns its PHP children and validates real JTL samples."""
import argparse
from collections import Counter
import csv
import json
import math
import os
from pathlib import Path
import shutil
import socket
import subprocess
import sys
import time
from urllib.request import urlopen

ROOT = Path(__file__).resolve().parents[2]
LABELS = {"GET health", "Buscar laptop", "Buscar laptop con filtros"}
SERVICES = {
    "alpha": (8101, "services/provider-alpha/public"),
    "beta": (8102, "services/provider-beta/public"),
    "gamma": (8103, "services/provider-gamma/public"),
    "bus": (8000, "apps/bus/public"),
}


def summarize_results(path, expected=45):
    """Read CSV, including assertion failures; no synthetic performance values."""
    with Path(path).open(encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        required = {"timeStamp", "elapsed", "label", "responseCode", "success", "failureMessage"}
        if not required.issubset(reader.fieldnames or []):
            raise ValueError("JTL missing required CSV fields")
        rows = list(reader)
    if not rows:
        raise ValueError("JMeter produced no samples")
    labels = Counter()
    failed = []
    elapsed = []
    timestamps = []
    for number, row in enumerate(rows, 1):
        duration, timestamp = float(row["elapsed"]), float(row["timeStamp"])
        if not math.isfinite(duration) or not math.isfinite(timestamp) or duration < 0 or timestamp < 0:
            raise ValueError("Invalid timing in JTL sample " + str(number))
        success = row["success"].strip().lower()
        if success not in {"true", "false"}:
            raise ValueError("Invalid success field in JTL sample " + str(number))
        elapsed.append(duration)
        timestamps.append(timestamp)
        labels[row["label"]] += 1
        if success != "true" or row["responseCode"] != "200" or row["failureMessage"].strip():
            failed.append({"sample": number, "label": row["label"], "http": row["responseCode"], "message": row["failureMessage"]})
    total = len(rows)
    seconds = (max(start + duration for start, duration in zip(timestamps, elapsed)) - min(timestamps)) / 1000
    if seconds <= 0:
        raise ValueError("JTL cannot establish a positive measurement interval")
    errors = []
    if total != expected:
        errors.append(f"Expected {expected} samples, received {total}")
    if set(labels) != LABELS or any(count != expected // 3 for count in labels.values()):
        errors.append("Expected 15 samples for each of the three plan requests")
    if failed:
        errors.append(f"{len(failed)} samples failed HTTP/JSON assertions or response checks")
    return {
        "samples": total,
        "successful": total - len(failed),
        "failed": len(failed),
        "error_percent": round(100 * len(failed) / total, 4),
        "average_ms": round(sum(elapsed) / total, 4),
        "throughput_requests_per_second": round(total / seconds, 4),
        "measurement_seconds": round(seconds, 4),
        "samples_by_request": dict(labels),
        "valid": not errors,
        "errors": errors,
        "failed_samples": failed,
    }


def run(jmeter):
    if os.environ.get("GITHUB_ACTIONS") != "true" or sys.platform != "linux":
        raise RuntimeError("This service runner is restricted to Linux GitHub Actions")
    php = shutil.which("php")
    if not php or not Path(jmeter).is_file():
        raise RuntimeError("PHP or the pinned JMeter binary is unavailable")
    output = ROOT / ".runtime/jmeter"
    output.mkdir(parents=True, exist_ok=True)
    if (output / "results.jtl").exists() or (output / "report").exists():
        raise RuntimeError("Refusing to append to results from another JMeter run")
    for port, _ in SERVICES.values():
        with socket.socket() as connection:
            connection.settimeout(0.2)
            if connection.connect_ex(("127.0.0.1", port)) == 0:
                raise RuntimeError(f"Port {port} is occupied; no duplicate service was started")

    processes, handles = [], []
    try:
        for name, (port, directory) in SERVICES.items():
            public = ROOT / directory
            log = (output / (name + ".log")).open("w", encoding="utf-8")
            handles.append(log)
            child = subprocess.Popen([php, "-S", f"127.0.0.1:{port}", "-t", str(public), str(public / "index.php")], cwd=ROOT, stdin=subprocess.DEVNULL, stdout=log, stderr=log)
            processes.append(child)
            for _ in range(50):
                if child.poll() is not None:
                    raise RuntimeError(f"Owned {name} process exited during startup")
                try:
                    with urlopen(f"http://127.0.0.1:{port}/health", timeout=2) as response:
                        health = json.load(response)
                        if health.get("status") == "ok" and (name != "bus" or health.get("database") == "ok"):
                            print(f"PASS JMeter precondition: {name} healthy on {port}", flush=True)
                            break
                except (OSError, ValueError):
                    pass
                time.sleep(0.1)
            else:
                raise RuntimeError(f"Health check failed for {name}")
        worker_log = (output / "worker.log").open("w", encoding="utf-8")
        handles.append(worker_log)
        worker = subprocess.Popen([php, str(ROOT / "scripts/cleanup-worker.php")], cwd=ROOT, stdin=subprocess.DEVNULL, stdout=worker_log, stderr=worker_log)
        processes.append(worker)
        versions = subprocess.run(["java", "-version"], text=True, capture_output=True, check=True, timeout=30)
        jmeter_version = subprocess.run([jmeter, "-v"], text=True, capture_output=True, check=True, timeout=30)
        (output / "versions.txt").write_text(versions.stdout + versions.stderr + jmeter_version.stdout + jmeter_version.stderr, encoding="utf-8")
        print((output / "versions.txt").read_text(encoding="utf-8"), flush=True)
        result = subprocess.run([
            jmeter, "-n", "-t", str(ROOT / "tests/jmeter/isa3-bus.jmx"),
            "-l", str(output / "results.jtl"), "-j", str(output / "jmeter.log"),
            "-e", "-o", str(output / "report"),
            "-Jjmeter.save.saveservice.output_format=csv",
            "-Jjmeter.save.saveservice.print_field_names=true",
            "-Jjmeter.save.saveservice.timestamp_format=ms",
            "-Jjmeter.save.saveservice.assertion_results_failure_message=true",
        ], cwd=ROOT, timeout=180)
        summary = summarize_results(output / "results.jtl")
        summary["jmeter_exit_code"] = result.returncode
        summary["valid"] = summary["valid"] and result.returncode == 0
        if result.returncode:
            summary["errors"].append(f"JMeter process exited with code {result.returncode}")
        (output / "summary.json").write_text(json.dumps(summary, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
        print("REAL JMETER RESULTS\n" + json.dumps(summary, indent=2, ensure_ascii=False), flush=True)
        return 0 if summary["valid"] else 1
    finally:
        for child in reversed(processes):
            if child.poll() is None:
                child.terminate()
                try:
                    child.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    child.kill()
                    child.wait()
        for handle in handles:
            handle.close()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--jmeter", required=True)
    options = parser.parse_args()
    try:
        sys.exit(run(options.jmeter))
    except Exception as error:
        print("FAIL JMeter: " + str(error), file=sys.stderr)
        sys.exit(1)
