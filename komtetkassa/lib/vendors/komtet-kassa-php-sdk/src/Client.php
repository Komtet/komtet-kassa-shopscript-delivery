<?php

/**
 * This file is part of the komtet/kassa-sdk library
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Komtet\KassaSdk;

use Komtet\KassaSdk\Exception\ClientException;

class Client
{
    /**
     * @var string
     */
    private $host = 'https://kassa.komtet.ru';

    /**
     * @var string
     */
    private $partner = null;

    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $secret;


    /**
     * @var array A list of headers to be masked in logs
     */
    private $maskedHeaders = ['Authorization', 'X-HMAC-Signature'];

    /**
     * @param string $key Shop ID
     * @param string $secret Secret key
     *
     * @return Client
     */
    public function __construct($key, $secret)
    {
        $this->key = $key;
        $this->secret = $secret;
    }

    /**
     * @param string $value
     *
     * @return Client
     */
    public function setHost($value)
    {
        $this->host = $value;

        return $this;
    }

    /**
     * @param string $value
     *
     * @return Client
     */
    public function setPartner($value)
    {
        $this->partner = $value;

        return $this;
    }

    /**
     * @param string $path
     * @param mixed $data
     *
     * @return mixed
     */
    public function sendRequest($path, $data = null)
    {
        if ($data === null) {
            $method = 'GET';
        } elseif (is_array($data)) {
            // Принудительно выставляем serialize_precision для корректной сериализации float
            if (version_compare(phpversion(), '7.1', '>=')) {
                ini_set( 'serialize_precision', 10 );
            }
            $method = 'POST';
            $data = json_encode($data);
        } else {
            throw new InvalidArgumentException('Unexpected type of $data, excepts array or null');
        }

        $url = sprintf('%s/%s', $this->host, $path);
        $signature = hash_hmac('md5', $method . $url . ($data ? $data : ''), $this->secret);

        $headers = [
            'Accept: application/json',
            sprintf('Authorization: %s', $this->key),
            sprintf('X-HMAC-Signature: %s', $signature)
        ];
        if (!empty($this->partner)) {
            $headers[] = sprintf('X-Partner-ID: %s', $this->partner);
        }
        if ($method == 'POST') {
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $response = curl_exec($ch);

        $error = null;
        if ($response === false) {
            $error = curl_error($ch);
        } else {
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($status !== 200) {
                $error = sprintf('Unexpected status (%s)', $status);
            }
        }
        curl_close($ch);
        if ($error !== null) {
            throw new ClientException($error);
        }

        return json_decode($response, true);
    }

    private function maskHeaders($headers)
    {
        return array_map(
            function($header) {
                $parts = explode(':', $header);
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                if (in_array($key, $this->maskedHeaders)) {
                    $value =  str_repeat('*', strlen($value) - 2) . substr($value, -2);
                }
                return [$key, $value];
            },
            $headers
        );
    }
}
