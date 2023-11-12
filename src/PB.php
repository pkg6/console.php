<?php

namespace Pkg6\Console;

class PB
{

    //显示模式 简洁
    const FORMAT_TYPE_NORMAL = 'normal';

    //总数
    protected $max;
    protected $step            = 0;
    protected $barWidth        = 28;
    public    $emptyBarChar    = '>';
    public    $progressChar    = '-';
    protected $customFormat    = null;
    protected $formatType      = 'default';
    protected $formatLineCount = 1;
    protected $startTime;
    protected $stepWidth       = 4;
    protected $messages        = [];
    protected $format;

    protected $terminalWidth;
    protected $terminalHeight;

    //格式
    protected $formats = [
        //默认
        'default'       => ' %current%/%max% [%bar%] %percent:3s%%  时间:%elapsed%/%estimated% 速度:%speed% 内存:%memory:6s%',
        'default_nomax' => ' %current% [%bar%] 时间:%elapsed:6s% 速度:%speed% 内存:%memory:6s%',

        //简洁
        'normal'        => ' %current%/%max% [%bar%] %percent:3s%%',
        'normal_nomax'  => ' %current% [%bar%]',
    ];

    /**
     * 创建进度条
     * @param int $max
     * @return $this
     */
    public static function create($max = 0)
    {
        return new self($max);
    }

    /**
     * ProgressBar constructor.
     * @param int $max
     */
    public function __construct($max = 0)
    {
        $this->setMax($max);
        $this->startTime = microtime(true);
        $this->write('loading...');
        $this->initTerminal();
    }

    /**
     * 进度 + N
     * @param int $step
     */
    public function next($step = 1)
    {
        $step = $this->step + $step;
        if ($this->max && $step > $this->max) {
            $this->max = $step;
        } elseif ($step < 0) {
            $step = 0;
        }
        $this->step = $step;
        $this->display();
    }

    /**
     * 结束
     */
    public function finish()
    {
        if (!$this->max) {
            $this->max = $this->step;
        }
        $this->step = $this->max;
        $this->display();
    }

    /**
     * @param $key
     * @param $message
     */
    public function setMessage($key, $message)
    {
        $this->messages[$key] = $message;
    }

    /**
     * 设置进度条宽度
     * @param $width
     */
    public function setBarWidth($width)
    {
        $this->barWidth = max(1, (int)$width);
    }

    /**
     * 显示
     */
    protected function display()
    {
        if (null === $this->format) {
            $this->setRealFormat();
        }
        $this->clear();
        $this->write($this->buildLine());
    }

    /**
     * 构建行内容
     * @return string
     */
    private function buildLine()
    {
        $regex    = "{%([a-z\-_]+)(?:\:([^%]+))?%}i";
        $callback = function ($matches) {
            $text    = $matches[0];
            $percent = $this->max ? (float)$this->step / $this->max : 0;
            switch ($matches[1]) {
                //最大
                case 'max':
                    $text = $this->max;
                    break;
                //当前进度
                case 'current':
                    $text = str_pad($this->step, $this->stepWidth, ' ', STR_PAD_LEFT);
                    break;
                //进度条
                case 'bar':
                    $completeBars = floor($this->max > 0 ? $percent * $this->barWidth : $this->step % $this->barWidth);
                    $display      = str_repeat($this->progressChar, $completeBars);
                    if ($completeBars < $this->barWidth) {
                        $emptyBars = $this->barWidth - $completeBars;
                        $display   .= str_repeat($this->emptyBarChar, $emptyBars);
                    }
                    $text = $display;
                    break;
                //进度百分比
                case 'percent':
                    $text = floor($percent * 100);
                    break;
                //使用内存
                case 'memory':
                    $text = $this->formatMemory(memory_get_usage(true));
                    break;
                //执行时间
                case 'elapsed':
                    $text = $this->formatTime(microtime(true) - $this->startTime);
                    break;
                //预计时间
                case 'estimated':
                    if ($this->max) {
                        $text = $this->formatTime(((microtime(true) - $this->startTime) / $this->step) * $this->max);
                    } else {
                        $text = '';
                    }
                    break;
                //速度
                case 'speed':
                    $time = (microtime(true) - $this->startTime);
                    if ($time && $this->step) {
                        $text = $this->formatSpeed($this->step / $time);
                    } else {
                        $text = '';
                    }
                    break;
                default:
                    if (isset($this->messages[$matches[1]])) {
                        $text = $this->messages[$matches[1]];
                    }
                    break;
            }

            if (isset($matches[2])) {
                $text = sprintf('%' . $matches[2], $text);
            }

            return $text;
        };
        $line     = preg_replace_callback($regex, $callback, $this->format);
        //获取最长的一行 width
        $linesWidth    = $this->getMessageMaxWidth($line);
        $terminalWidth = $this->getTerminalWidth();
        if ($linesWidth <= $terminalWidth) {
            return $line;
        }
        $this->setBarWidth($this->barWidth - $linesWidth + $terminalWidth);
        return preg_replace_callback($regex, $callback, $this->format);
    }

    /**
     * 获取输出文本 width
     * @param $messages
     */
    private function getMessageMaxWidth($messages)
    {
        $linesLength = array_map(function ($subLine) {
            $string = preg_replace("/\033\[[^m]*m/", '', $subLine);
            return $this->strLen($string);
        }, explode("\n", $messages));
        return max($linesLength);
    }

    /**
     * 清空
     */
    protected function clear()
    {
        //光标移到最左边
        $this->write("\x0D");
        //清除光标所在行的所有字符
        $this->write("\x1B[2K");
        //清除多行
        if ($this->formatLineCount > 0) {
            $this->write(str_repeat("\x1B[1A\x1B[2K", $this->formatLineCount));
        }
    }

    /**
     * 设置格式化
     * @param $format
     */
    private function setRealFormat()
    {
        $format = $this->customFormat ?: $this->formatType;
        if (!$this->max && isset($this->formats[$format . '_nomax'])) {
            $this->format = $this->formats[$format . '_nomax'];
        } else if (isset($this->formats[$format])) {
            $this->format = $this->formats[$format];
        } else {
            $this->format = $format;
        }
        $this->formatLineCount = substr_count($this->format, "\n");
    }

    /**
     * 设置最大值
     * @param int $max
     */
    public function setMax($max = 0)
    {
        $this->max = max(0, $max);
        if ($max) $this->stepWidth = $this->strLen($max);
    }

    /**
     * 设置模式类型
     * @param $type
     */
    public function setFormatType($type)
    {
        $this->formatType = $type;
    }

    /**
     * 设置自定义 显示格式
     * @param $format
     */
    public function setCustomFormat($format)
    {
        $this->customFormat = $format;
    }

    /**
     * 输出
     * @param $message
     */
    protected function write($message)
    {
        echo $message;
    }

    /**
     * 获取字符长度
     * @param $string
     * @return int
     */
    protected function strLen($string)
    {
        if (false === $encoding = mb_detect_encoding($string, null, true)) {
            return strlen($string);
        }
        return mb_strwidth($string, $encoding);
    }

    /**
     * 格式化内存
     * @param $memory
     * @return string
     */
    protected function formatMemory($memory)
    {
        if ($memory >= 1024 * 1024 * 1024) {
            return sprintf('%.1f GB', $memory / 1024 / 1024 / 1024);
        }

        if ($memory >= 1024 * 1024) {
            return sprintf('%.1f MB', $memory / 1024 / 1024);
        }

        if ($memory >= 1024) {
            return sprintf('%d KB', $memory / 1024);
        }
        return sprintf('%d B', $memory);
    }

    /**
     * 格式化时间
     * @param  $secs
     * @return mixed|string
     */
    protected function formatTime($time)
    {
        $minutes = floor($time / 60);
        $seconds = (int)$time % 60;
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * 格式化速度
     * @param $speed
     * @return mixed
     */
    protected function formatSpeed($speed)
    {
        if ($speed > 0.9) {
            return round($speed) . '/s';
        } else if ($speed * 60 > 1) {
            return round($speed * 60) . '/m';
        } else {
            return round($speed * 3600) . '/h';
        }
    }

    /**
     * 获取命令行宽度
     * @return int
     */
    protected function getTerminalWidth()
    {
        $width = getenv('COLUMNS');
        if (false !== $width) {
            return (int)trim($width);
        }
        return $this->terminalWidth ?: 80;
    }

    /**
     * 获取命令行高度
     * @return int
     */
    public function getTerminalHeight()
    {
        $height = getenv('LINES');
        if (false !== $height) {
            return (int)trim($height);
        }
        return $this->terminalHeight ?: 50;
    }

    protected function initTerminal()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            if (preg_match('/^(\d+)x(\d+)(?: \((\d+)x(\d+)\))?$/', trim(getenv('ANSICON')), $matches)) {
                // extract [w, H] from "wxh (WxH)"
                // or [w, h] from "wxh"
                $this->terminalWidth  = (int)$matches[1];
                $this->terminalHeight = isset($matches[4]) ? (int)$matches[4] : (int)$matches[2];
            } elseif (null !== $dimensions = $this->getConsoleMode()) {
                // extract [w, h] from "wxh"
                $this->terminalWidth  = (int)$dimensions[0];
                $this->terminalHeight = (int)$dimensions[1];
            }
        } elseif ($sttyString = $this->getSttyColumns()) {
            if (preg_match('/rows.(\d+);.columns.(\d+);/i', $sttyString, $matches)) {
                // extract [w, h] from "rows h; columns w;"
                $this->terminalWidth  = (int)$matches[2];
                $this->terminalHeight = (int)$matches[1];
            } elseif (preg_match('/;.(\d+).rows;.(\d+).columns/i', $sttyString, $matches)) {
                // extract [w, h] from "; h rows; w columns"
                $this->terminalWidth  = (int)$matches[2];
                $this->terminalHeight = (int)$matches[1];
            }
        }
    }

    protected function getConsoleMode()
    {
        if (!function_exists('proc_open')) {
            return;
        }
        $descriptorspec = array(
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $process        = proc_open('mode CON', $descriptorspec, $pipes, null, null, array('suppress_errors' => true));
        if (is_resource($process)) {
            $info = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            if (preg_match('/--------+\r?\n.+?(\d+)\r?\n.+?(\d+)\r?\n/', $info, $matches)) {
                return array((int)$matches[2], (int)$matches[1]);
            }
        }
    }

    protected function getSttyColumns()
    {
        if (!function_exists('proc_open')) {
            return;
        }

        $descriptorspec = array(
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        $process = proc_open('stty -a | grep columns', $descriptorspec, $pipes, null, null, array('suppress_errors' => true));
        if (is_resource($process)) {
            $info = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return $info;
        }
    }
}