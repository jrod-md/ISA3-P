<?php
declare(strict_types=1);

namespace Marketplace\Bus\Http;

use Marketplace\Bus\Config\Config;

final class ProviderClient
{
    /** @return array<string,array{ok:bool,payload?:array,message?:string,duration_ms:int,http_code:int}> */
    public function search(array $providerKeys, array $query): array
    {
        $multi = curl_multi_init();
        $handles = [];
        $started = [];

        foreach ($providerKeys as $key) {
            $baseUrl = Config::string('PROVIDER_' . strtoupper($key) . '_URL');
            $url = rtrim($baseUrl, '/') . '/api/products?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT_MS => Config::int('PROVIDER_CONNECT_TIMEOUT_MS', 500),
                CURLOPT_TIMEOUT_MS => Config::int('PROVIDER_TIMEOUT_MS', 1500),
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $handles[$key] = $handle;
            $started[$key] = hrtime(true);
            curl_multi_add_handle($multi, $handle);
        }

        do {
            $status = curl_multi_exec($multi, $running);
            if ($running > 0) {
                curl_multi_select($multi, 0.2);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $responses = [];
        foreach ($handles as $key => $handle) {
            $body = curl_multi_getcontent($handle);
            $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $durationMs = (int) round((hrtime(true) - $started[$key]) / 1_000_000);
            $curlError = curl_error($handle);
            if ($curlError !== '') {
                $responses[$key] = ['ok' => false, 'message' => $curlError, 'duration_ms' => $durationMs, 'http_code' => $httpCode];
            } elseif ($httpCode < 200 || $httpCode >= 300) {
                $responses[$key] = ['ok' => false, 'message' => "HTTP {$httpCode}", 'duration_ms' => $durationMs, 'http_code' => $httpCode];
            } else {
                try {
                    $payload = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($payload)) {
                        throw new \UnexpectedValueException('JSON root is not an object');
                    }
                    $responses[$key] = ['ok' => true, 'payload' => $payload, 'duration_ms' => $durationMs, 'http_code' => $httpCode];
                } catch (\Throwable) {
                    $responses[$key] = ['ok' => false, 'message' => 'Invalid JSON response', 'duration_ms' => $durationMs, 'http_code' => $httpCode];
                }
            }
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }
        curl_multi_close($multi);
        return $responses;
    }
}

