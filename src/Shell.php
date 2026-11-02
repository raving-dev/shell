<?php

namespace RavingDev\Shell;

use RavingDev\Shell\Exception\EnvNameException;

class Shell
{
    protected array $arguments;

    /**
     * @var true
     */
    protected bool $withErrors = false;
    protected ?string $output = null;
    protected array $env = [];
    protected bool $safe = true;
    /**
     * @var true
     */
    protected bool $append = false;
    protected bool $detach = false;
    protected bool $mergeError = false;
    protected ?int $timeout = null;

    protected array $stdout = [];
    protected array $stderr = [];
    protected int $exitCode = 0;

    protected bool $executed = false;

    protected ?string $stderrFile = null;

    public function __construct(protected string $command, string ...$arguments)
    {
        $this->arguments = $arguments;
    }

    public static function command(string $command, string ...$arguments): static
    {
        return new static($command, ...$arguments);
    }

    public function sendTo(string $output): static
    {
        $this->output = $output;

        return $this;
    }

    public function sendAppend(): static
    {
        $this->append = true;

        return $this;
    }

    public function sendToDevNull(): static
    {
        $this->sendTo('/dev/null');
        $this->mergeError();

        return $this;
    }

    public function mergeError(): static
    {
        $this->mergeError = true;

        return $this;
    }

    public function env(string $name, $value): static
    {
        if (preg_match('~\s~', $name)) {
            throw new EnvNameException('Environment variable name must not contain spaces.');
        }

        $name = trim($name);

        if ($name === '') {
            throw new EnvNameException('Environment variable name must not be empty.');
        }

        $this->env[$name] = $value;

        return $this;
    }

    public function envs(array $envs): static
    {
        $this->env = $envs;

        return $this;
    }

    public function unsafe(): static
    {
        $this->safe = false;

        return $this;
    }

    protected function escape(string $string): string
    {
        if ($this->safe) {
            return escapeshellarg($string);
        }

        return sprintf('"%s"', addslashes($string));
    }

    public function detach(): static
    {
        $this->detach = true;

        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    protected function build(bool $internal = false): string
    {
        $subshell = (bool)$this->env;

        $command = [];
        $envs = [];

        foreach ($this->env as $name => $value) {
            $envs[] = sprintf('export %s=%s;', $name, $this->escape($value));
        }

        if ($envs) {
            $command[] = implode('; ', $envs);
        }

        if ($this->timeout !== null) {
            $command[] = sprintf('timeout %d', $this->timeout);
        }

        $command[] = $this->escape($this->command);

        $command = array_merge($command, array_map([$this, 'escape'], $this->arguments));

        if ($this->output) {
            $command[] = sprintf(' %s %s', $this->append ? '>>' : '>', $this->escape($this->output));
        }

        $result = implode(' ', $command);

        if ($subshell) {
            $result = sprintf('(%s)', $result);
        }

        if ($internal) {
            if ($this->mergeError) {
                $result .= ' 2>&1';
            } elseif ($this->stderrFile) {
                $result .= ' 2>' . $this->escape($this->stderrFile);
            }
        }

        if ($this->detach) {
            $result .= ' &';
        }

        return $result;
    }

    public function execute(): bool
    {
        $this->executed = true;

        $this->stderrFile = tempnam(sys_get_temp_dir(), 'shell_err_');
        $cmd = $this->build(true);

        exec($cmd, $this->stdout, $this->exitCode);

        $this->stderr = file($this->stderrFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        @unlink($this->stderrFile);
        $this->stderrFile = null;

        return $this->exitCode === 0;
    }

    public function output(bool $trim = false): string
    {
        if (!$this->executed) {
            $this->execute();
        }

        $result = implode("\n", $this->stdout);

        if ($trim) {
            $result = trim($result);
        }

        return $result;
    }

    public function error(bool $trim = false): string
    {
        if (!$this->executed) {
            $this->execute();
        }

        $result = implode("\n", $this->stderr);

        if ($trim) {
            $result = trim($result);
        }

        return $result;
    }

    public function code(): int
    {
        if (!$this->executed) {
            $this->execute();
        }

        return $this->exitCode;
    }

    public function __toString(): string
    {
        return $this->build();
    }
}
