<?php

class CurlResponse
{
    public string $version = '';
    public int $statusCode = 0;
    public string $status = '';
    public string $body = '';
    public array $headers = [];

    /**
     * @param string $response
     * @param array $info = curl_getinfo()
     */
    public function __construct(string $response, array $info)
    {
        [$lastHttp, $this->body] = $this->splitResponse($response, $info);

        [$httpString, $headers] = explode("\r\n", $lastHttp, 2);

        [$this->version, $this->statusCode, $this->status] =
            explode(' ', $httpString, 3);

        foreach (explode("\r\n", $headers) as $header) {
            [$key, $value] = explode(':', $header, 2);
            $this->headers[strtolower($key)] = trim($value);
        }
    }

    public function __toString()
    {
        return (string) $this->body;
    }

    protected function splitResponse(string $response, array $info): array
    {
        $size = $info['header_size'];
        $headers = substr($response, 0, $size - 4);
        $body = substr($response, $size);

        $ptr = strrpos($headers, "\r\n\r\n");
        return $ptr !== false ?
            [substr($headers, $ptr + 4), $body] :
            [$headers, $body];
    }
}
