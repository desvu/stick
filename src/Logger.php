<?php declare(strict_types=1);

/**
 * This file is part of the eghojansu/stick library.
 *
 * (c) Eko Kurniawan <ekokurniawanbs@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fal\Stick;

final class Logger
{
    /** Log frequency */
    const
        LOG_DAILY = 'daily',
        LOG_WEEKLY = 'weekly',
        LOG_MONTHLY = 'monthly';

    /** Log level */
    const
        LEVEL_EMERGENCY = 'emergency',
        LEVEL_ALERT = 'alert',
        LEVEL_CRITICAL = 'critical',
        LEVEL_ERROR = 'error',
        LEVEL_WARNING = 'warning',
        LEVEL_NOTICE = 'notice',
        LEVEL_INFO = 'info',
        LEVEL_DEBUG = 'debug';

    /** Log level value */
    const LEVELS = [
        self::LEVEL_EMERGENCY => 0,
        self::LEVEL_ALERT => 1,
        self::LEVEL_CRITICAL => 2,
        self::LEVEL_ERROR => 3,
        self::LEVEL_WARNING => 4,
        self::LEVEL_NOTICE => 5,
        self::LEVEL_INFO => 6,
        self::LEVEL_DEBUG => 7,
    ];

    /** @var string */
    private $extension = 'log';

    /** @var string */
    private $dateFormat = 'Y-m-d G:i:s.u';

    /** @var string */
    private $logFrequency = self::LOG_DAILY;

    /** @var string */
    private $prefix = 'log_';

    /** @var string */
    private $dir;

    /** @var string */
    private $logLevelThreshold;

    public function __construct(string $dir, string $logLevelThreshold = self::LEVEL_DEBUG)
    {
        $this->dir = $dir;
        $this->logLevelThreshold = $logLevelThreshold;
    }

    public function emergency(string $message, array $context = []): Logger
    {
        return $this->log(self::LEVEL_EMERGENCY, $message, $context);
    }

    public function alert(string $message, array $context = []): Logger
    {
        return $this->log(self::LEVEL_ALERT, $message, $context);
    }

    public function critical(string $message, array $context = []): Logger
    {
        return $this->log(self::LEVEL_CRITICAL, $message, $context);
    }

    public function error(string $message, array $context = []): Logger
    {
        return $this->log(self::LEVEL_ERROR, $message, $context);
    }

    public function warning(string $message, array $context = []): Logger
    {
        return $this->log(self::LEVEL_WARNING, $message, $context);
    }

    public function notice(string $message, array $context = []): Logger
    {
        return $this->log(self::LEVEL_NOTICE, $message, $context);
    }

    public function info(string $message, array $context = []): Logger
    {
        return $this->log(self::LEVEL_INFO, $message, $context);
    }

    public function debug(string $message, array $context = []): Logger
    {
        return $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    public function log(string $level, string $message, array $context = []): Logger
    {
        if (self::LEVELS[$this->logLevelThreshold] < (self::LEVELS[$level] ?? 100)) {
            return $this;
        }

        $this->write($message, $context, $level);

        return $this;
    }

    public function files(\DateTime $from = null, \DateTime $to = null): array
    {
        $pattern = $this->dir . $this->prefix . '*.' . $this->extension;
        $start = strlen($this->prefix);
        $end = 10;
        $to = $to ?? $from;

        return array_filter(glob($pattern), function($file) use ($from, $to, $start, $end) {
            try {
                $createdAt = new \DateTime(substr(basename($file), $start, $end));

                return $from ? $createdAt >= $from && $createdAt <= $to : true;
            } catch (\Exception $e) {
                return false;
            }
        });
    }

    public function clear(\DateTime $from = null, \DateTime $to = null): Logger
    {
        foreach ($this->files($from, $to) as $file) {
            unlink($file);
        }

        return $this;
    }

    /**
     * Get dateFormat
     *
     * @return string
     */
    public function getDateFormat(): string
    {
        return $this->dateFormat;
    }

    /**
     * Set dateFormat
     *
     * @param string $dateFormat
     * @return Logger
     */
    public function setDateFormat(string $dateFormat): Logger
    {
        $this->dateFormat = $dateFormat;

        return $this;
    }

    /**
     * Get dir
     *
     * @return string
     */
    public function getDir(): string
    {
        return $this->dir;
    }

    /**
     * Set dir
     *
     * @param string $dir
     * @return Logger
     */
    public function setDir(string $dir): Logger
    {
        $this->dir = $dir;

        return $this;
    }

    /**
     * Get logLevelThreshold
     *
     * @return string
     */
    public function getLogLevelThreshold(): string
    {
        return $this->logLevelThreshold;
    }

    /**
     * Set logLevelThreshold
     *
     * @param string $logLevelThreshold
     * @return Logger
     */
    public function setLogLevelThreshold(string $logLevelThreshold): Logger
    {
        if (!isset(self::LEVELS[$logLevelThreshold])) {
            throw new \DomainException('Invalid log level: ' . $logLevelThreshold);
        }

        $this->logLevelThreshold = $logLevelThreshold;

        return $this;
    }

    /**
     * Get extension
     *
     * @return string
     */
    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * Set extension
     *
     * @param string $extension
     * @return Logger
     */
    public function setExtension(string $extension): Logger
    {
        $this->extension = $extension;

        return $this;
    }

    /**
     * Get prefix
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Set prefix
     *
     * @param string $prefix
     * @return Logger
     */
    public function setPrefix(string $prefix): Logger
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Get logFrequency
     *
     * @return string
     */
    public function getLogFrequency(): string
    {
        return $this->logFrequency;
    }

    /**
     * Set logFrequency
     *
     * @param string $logFrequency
     * @return Logger
     */
    public function setLogFrequency(string $logFrequency): Logger
    {
        $this->logFrequency = $logFrequency;

        return $this;
    }

    private function write(string $message, array $context, string $level): void
    {
        Helper::mkdir($this->dir);

        $content = date($this->dateFormat) . ' ' . $level . ' ' . Helper::interpolate($message, $context, '{}') . PHP_EOL;

        Helper::write($this->resolveFile(), $content, true);
    }

    private function resolveFile(): string
    {
        $prefix = $this->dir . $this->prefix;
        $ext = '.' . $this->extension;

        switch ($this->logFrequency) {
            case self::LOG_DAILY:
                return $prefix . date('Y-m-d') . $ext;

            case self::LOG_WEEKLY:
                return $this->findThisWeekFile($prefix, $ext);

            case self::LOG_MONTHLY:
                return $this->findThisMonthFile($prefix, $ext);

            default:
                return $this->findDefaultFile($prefix, $ext);
        }
    }

    private function findThisWeekFile(string $prefix, string $ext): string
    {
        $start = strlen($prefix) + 8;
        $currentWeek = floor(date('d') / 7);

        foreach (glob($prefix . date('Y-m') . '*' . $ext) as $file) {
            $day = substr($file, $start, 2);
            $week = floor($day / 7);

            if ($week === $currentWeek) {
                return $file;
            }
        }

        return $prefix . date('Y-m-d') . $ext;
    }

    private function findThisMonthFile(string $prefix, string $ext): string
    {
        $files = glob($prefix . date('Y-m') . '*' . $ext);

        return $files ? $files[0] : $prefix . date('Y-m-d') . $ext;
    }

    private function findDefaultFile(string $prefix, string $ext): string
    {
        $files = glob($prefix . '*' . $ext);

        return $files ? $files[0] : $prefix . date('Y-m-d') . $ext;
    }
}