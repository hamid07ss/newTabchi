<?php
/*
Copyright 2016-2017 Daniil Gentili
(https://daniil.it)
This file is part of MadelineProto.
MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU Affero General Public License for more details.
You should have received a copy of the GNU General Public License along with MadelineProto.
If not, see <http://www.gnu.org/licenses/>.
*/

namespace danog\MadelineProto;

class Exception extends \Exception
{
    use TL\PrettyException;

    public function __toString()
    {
        return $this->file === 'MadelineProto' ? $this->message : '\danog\MadelineProto\Exception'.($this->message !== '' ? ': ' : '').$this->message.' in '.$this->file.':'.$this->line.PHP_EOL.'TL Trace:'.PHP_EOL.$this->getTLTrace();
    }

    public function __construct($message = null, $code = 0, Exception $previous = null, $file = null, $line = null)
    {
        $this->prettify_tl();
        if ($file !== null) {
            if (basename($file) === 'Threaded.php') {
                $line = debug_backtrace(0)[2]['line'];
                $file = debug_backtrace(0)[2]['file'];
            }
            $this->file = $file;
        }
        if ($line !== null) {
            $this->line = $line;
        }
        parent::__construct($message, $code, $previous);

        if (\danog\MadelineProto\Logger::$constructed) {
            \danog\MadelineProto\Logger::log([$message.' in '.basename($this->file).':'.$this->line], \danog\MadelineProto\Logger::FATAL_ERROR);
        }
        if (in_array($message, ['The session is corrupted!', 'Re-executing query...', 'I had to recreate the temporary authorization key', 'This peer is not present in the internal peer database', "Couldn't get response", 'Chat forbidden', 'The php-libtgvoip extension is required to accept and manage calls. See daniil.it/MadelineProto for more info.', 'File does not exist', 'Please install this fork of phpseclib: https://github.com/danog/phpseclib'])) {
            return;
        }
        if (strpos($message, 'pg_query') !== false || strpos($message, 'Undefined variable: ') !== false || strpos($message, 'socket_write') !== false || strpos($message, 'socket_read') !== false || strpos($message, 'Received request to switch to DC ') !== false || strpos($message, "Couldn't get response") !== false || strpos($message, 'Re-executing query...') !== false || strpos($message, "Couldn't find peer by provided") !== false || strpos($message, 'id.pwrtelegram.xyz') !== false || strpos($message, 'Please update ') !== false || strpos($message, 'posix_isatty') !== false) {
            return;
        }
        \Rollbar\Rollbar::log(\Rollbar\Payload\Level::error(), $this, debug_backtrace(0));
    }

    /**
     * ExceptionErrorHandler.
     *
     * Error handler
     */
    public static function ExceptionErrorHandler($errno = 0, $errstr = null, $errfile = null, $errline = null)
    {
        // If error is suppressed with @, don't throw an exception
        if (error_reporting() === 0) {
            return true; // return true to continue through the others error handlers
        }

        throw new self($errstr, $errno, null, $errfile, $errline);
    }
}
