<?php

class Storage
{
    protected const ID_LENGTH = 8;
    protected const GIT_DIR = ROOT_DIR . '/git';
    protected const GIT_COMMAND =
        'git add . && git commit -am "%s" && git push %s';

    protected string $gitTarget;

    public __construct(string $gitTarget)
    {
        $this->gitTarget = $gitTarget;
    }

    /** @noinspection MkdirRaceConditionInspection */
    public function storeId(string $id, string $data): void
    {
        $id = $this->padStart($id);
        $path = self::GIT_DIR . '/' . chunk_split(substr($id, 0, -2), 2, '/');
        try {
            mkdir($path, 0755, true);
        } catch (Throwable $ignored) {
            if (! is_dir($path)) {
                throw new RuntimeException("Directory \"$path\" was not created");
            }
        }
        file_put_contents($path . $id, $data);
    }

    public function storeFormats(string $formats): void
    {
        file_put_contents(self::GIT_DIR . '/formats.json', $formats);
    }

    public function __destruct()
    {
        chdir(self::GIT_DIR);
        $command = sprintf(self::GIT_COMMAND, date('Y-m-d'), $this->gitTarget);
        passthru($command, $returnCode);
        if ($returnCode) {
            throw new RuntimeException("git returned $returnCode");
        }
    }

    protected function padStart(string $string): string
    {
        return ($length = self::ID_LENGTH - strlen($string)) > 0 ?
            str_repeat('0', $length) . $string :
            $string;
    }
}
