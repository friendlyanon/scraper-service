<?php

class Curl
{
    public ?string $cookieFile = null;
    public bool $followRedirects = true;
    public array $headers;
    public array $options;
    public ?string $referer = null;
    public ?string $userAgent = null;
    /** @var string|false */
    protected $error = false;
    /** @var resource */
    protected $request;

    public function __construct(array $options = [], array $headers = [])
    {
        $this->options = $options;
        $this->headers = $headers;
    }

    public function error()
    {
        return $this->error;
    }

    /**
     * @param string $url
     * @param array|string $data
     * @return CurlResponse|false
     */
    public function get(string $url, $data = [])
    {
        if (! empty($data)) {
            $url .= strpos($url, '?') !== false ? '&' : '?';
            $url .= is_string($data) ? $data : http_build_query($data, '', '&');
        }

        return $this->request('GET', $url);
    }

    /**
     * @param string $url
     * @return CurlResponse|false
     */
    public function post(string $url)
    {
        return $this->request('POST', $url);
    }

    /**
     * @param string $method
     * @param string $url
     * @return CurlResponse|false
     */
    public function request(string $method, string $url)
    {
        $this->error = false;
        $request = $this->request = curl_init();
        $method = strtoupper($method);

        $this->setRequestMethod($method);
        $this->setRequestOptions($url);
        $this->setRequestHeaders();

        $response = curl_exec($request);

        if ($response) {
            $response = new CurlResponse($response, curl_getinfo($request));
        } else {
            $this->error = curl_errno($request) . ' - ' . curl_error($request);
        }

        curl_close($request);

        return $response;
    }

    protected function setRequestHeaders(): void
    {
        $headers = [];
        foreach ($this->headers as $key => $value) {
            $headers[] = strtolower($key) . ': ' . $value;
        }
        curl_setopt($this->request, CURLOPT_HTTPHEADER, $headers);
    }

    protected function setRequestMethod(string $method): void
    {
        static $options = [
            'HEAD' => CURLOPT_NOBODY,
            'GET' => CURLOPT_HTTPGET,
            'POST' => CURLOPT_POST,
        ];

        if (isset($options[$method])) {
            curl_setopt($this->request, $options[$method], true);
        } else {
            curl_setopt($this->request, CURLOPT_CUSTOMREQUEST, $method);
        }
    }

    protected function setRequestOptions(string $url): void
    {
        curl_setopt($this->request, CURLOPT_URL, $url);
        curl_setopt($this->request, CURLOPT_HEADER, true);
        curl_setopt($this->request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->request, CURLOPT_USERAGENT, $this->userAgent);

        if ($this->cookieFile) {
            curl_setopt($this->request, CURLOPT_COOKIEFILE, $this->cookieFile);
            curl_setopt($this->request, CURLOPT_COOKIEJAR, $this->cookieFile);
        }

        if ($this->followRedirects) {
            curl_setopt($this->request, CURLOPT_FOLLOWLOCATION, true);
        }

        if ($this->referer) {
            curl_setopt($this->request, CURLOPT_REFERER, $this->referer);
        }

        foreach ($this->options as $option => $value) {
            if (($key = constant('CURLOPT_' . strtoupper($option))) !== null) {
                curl_setopt($this->request, $key, $value);
            }
        }
    }
}
