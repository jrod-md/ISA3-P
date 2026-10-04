"""Validator tests use synthetic CSV fixtures, never reported as JMeter runs."""
import csv
from pathlib import Path
import tempfile
import unittest
from run_ci import LABELS, summarize_results


class JMeterResultValidationTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.path = Path(self.directory.name) / "fixture.jtl"
        self.rows = []
        for i in range(45):
            self.rows.append({"timeStamp": str(1000 + 100 * i), "elapsed": "100", "label": sorted(LABELS)[i % 3], "responseCode": "200", "success": "true", "failureMessage": ""})

    def tearDown(self):
        self.directory.cleanup()

    def summary(self):
        with self.path.open("w", encoding="utf-8", newline="") as handle:
            writer = csv.DictWriter(handle, fieldnames=list(self.rows[0]))
            writer.writeheader()
            writer.writerows(self.rows)
        return summarize_results(self.path)

    def test_successful_fixture_calculates_measured_values(self):
        result = self.summary()
        self.assertTrue(result["valid"])
        self.assertEqual((45, 45, 0, 0), (result["samples"], result["successful"], result["failed"], result["error_percent"]))
        self.assertEqual(100, result["average_ms"])
        self.assertEqual(10, result["throughput_requests_per_second"])

    def test_assertion_failure_rejects_http_200(self):
        self.rows[0].update(success="false", failureMessage="JSON assertion failed")
        result = self.summary()
        self.assertFalse(result["valid"])
        self.assertEqual(1, result["failed"])

    def test_http_failure_rejects_even_forged_success(self):
        self.rows[0]["responseCode"] = "503"
        self.assertFalse(self.summary()["valid"])

    def test_failure_message_rejects_even_success_true(self):
        self.rows[0]["failureMessage"] = "HTTP assertion failed"
        self.assertFalse(self.summary()["valid"])

    def test_partial_run_is_not_a_successful_test(self):
        self.rows.pop()
        self.assertFalse(self.summary()["valid"])

    def test_wrong_requests_are_rejected(self):
        self.rows[0]["label"] = "Wrong request"
        self.assertFalse(self.summary()["valid"])

    def test_invalid_timing_rejected(self):
        self.rows[0]["elapsed"] = "NaN"
        with self.assertRaises(ValueError):
            self.summary()

    def test_invalid_success_rejected(self):
        self.rows[0]["success"] = "unknown"
        with self.assertRaises(ValueError):
            self.summary()

    def test_missing_or_empty_report_rejected(self):
        with self.assertRaises(FileNotFoundError):
            summarize_results(self.path)
        self.path.write_text("label,success\n", encoding="utf-8")
        with self.assertRaises(ValueError):
            summarize_results(self.path)
        self.path.write_text(",".join(self.rows[0]) + "\n", encoding="utf-8")
        with self.assertRaises(ValueError):
            summarize_results(self.path)
