<?php

class App
{
    /* @var int[] */
    public array $lastId;
    /** @var array<string, int> */
    protected array $map;
    /** @var string[] */
    protected array $tags;
    protected string $user;
    protected string $pass;
    /** @var string[] */
    protected array $pathKeys;
    protected Storage $storage;
    protected string $scrapeTarget;

    public function __construct(
        array $map,
        ?array $lastId,
        array $options
    ) {
        $this->map = $map;
        $this->ensureLastId($this->tags = $options['tags'], $lastId);
        $this->user = $options['user'];
        $this->pass = $options['api_key'];
        $this->pathKeys = $options['path_keys'];
        $this->scrapeTarget = $options['scrape_target'];
        $this->storage = new Storage($options['git_target']);
    }

    public function handle(): self
    {
        foreach ($this->rowGetter() as $row) {
            foreach ($this->processData($row) as $id => $data) {
                $this->storage->storeId($id, $data);
            }
        }

        $this->storage->storeFormats(jsonEncode(array_flip($this->map)));
        return $this;
    }

    protected function ensureLastId(array $tags, ?array $lastId): void
    {
        if ($lastId === null) {
            $this->lastId = array_fill(0, count($tags), 0);
        } elseif (count($lastId) !== ($length = count($tags))) {
            $zeros = array_fill(0, count($tags), 0);
            $this->lastId = array_slice($lastId + $zeros, 0, $length);
        } else {
            $this->lastId = $lastId;
        }
    }

    /**
     * TODO: clean up
     */
    protected function rowGetter(): Generator
    {
        $curl = new Curl([
            'httpauth' => CURLAUTH_BASIC,
            'userpwd' => "{$this->user}:{$this->pass}",
        ]);
        $beforeId = null;
        $index = 0;
        $limit = count($this->tags);
        $ids = [];
        $next = function () use (&$ids, &$index, &$beforeId) {
            $beforeId = null;
            if (! empty($ids)) {
                sort($ids);
                $this->lastId[$index] = end($ids);
            }
            $ids = [];
            ++$index;
        };

        while ($index < $limit) {
            $tag = $this->makeTag($index, $beforeId);
            if ($tag === null) {
                $next();
                continue;
            }

            $this->log("Tag: $tag, beforeId: $beforeId, lastId: {$this->lastId[$index]}");

            $r = $curl->get(sprintf($this->scrapeTarget, $tag));
            $this->log("Status from target: $r->statusCode");
            if (($result = $this->processResponse($r)) === null) {
                $this->log("Can't decode target response:\n$r->body");
                continue;
            }
            $r = null;

            if (count($result) === 0) {
                $next();
                continue;
            }

            $resultIds = array_map(static fn($o) => $o['id'], $result);
            if (in_array($this->lastId[$index], $resultIds, true)) {
                $next();
            } else {
                sort($resultIds);
                $beforeId = $resultIds[0];
                foreach ($resultIds as $id) {
                    $ids[] = $id;
                }
            }

            yield $result;
        }
    }

    protected function makeTag(int $index, ?int $beforeId): ?string
    {
        $tag = $this->tags[$index];
        if ($beforeId === null) {
            return $tag;
        }

        if ($beforeId <= $this->lastId[$index]) {
            return null;
        }

        return "$tag+id:<$beforeId";
    }

    /**
     * @noinspection PhpMissingBreakStatementInspection
     * @param CurlResponse $r
     * @return array|null
     */
    protected function processResponse(CurlResponse $r): ?array
    {
        switch ($r->statusCode) {
            case 200:
                return jsonDecode($r->body);
            case 429:
                sleep(5);
            default:
                return null;
        }
    }

    protected function processData(array $data): Generator
    {
        foreach ($data as $row) {
            yield $row['id'] => $this->makeEntry($row);
        }
    }

    protected function makeEntry(array $row): string
    {
        $patterns = [];
        $extensions = [];

        $hash = $row['md5'];
        $fragments = $this->getHashFragments($hash);
        $replacer = __CLASS__ . '::replacePattern';
        foreach ($this->pathKeys as $key) {
            $url = $row[$key];
            $extension = $extensions[] = $this->getExtension($url);
            $regex = "#($fragments)?/([^/]*?)$hash\.$extension#";
            $template = preg_replace_callback($regex, $replacer, $url);

            if (isset($this->map[$template])) {
                $patterns[] = $this->map[$template];
                continue;
            }

            $number = 0;
            if (count($numbers = array_values($this->map)) > 0) {
                sort($numbers);
                $number = end($numbers) + 1;
            }

            $this->log("New pattern: $template - $number");
            $this->map[$template] = ($patterns[] = $number);
            file_put_contents(MAP_PATH, serialize($this->map));
        }

        $patterns = $patterns[0] << 20 | $patterns[1] << 10 | $patterns[2];
        $extensions = implode(',', $extensions);
        return "$patterns:$extensions:$hash";
    }

    protected function getExtension(string $string): string
    {
        return substr($string, strrpos($string, '.') + 1);
    }

    protected function getHashFragments(string $hash): string
    {
        return substr($hash, 0, 2) . '/' . substr($hash, 2, 2);
    }

    protected function log($message): void
    {
        printf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
    }

    /** @noinspection PhpUnused */
    public static function replacePattern(array $matches): string
    {
        $replacement = "/{$matches[2]}{0}.{1}";
        return $matches[1] !== '' ?
            '{2}/{3}' . $replacement :
            $replacement;
    }
}
