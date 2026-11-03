<?php
/**
 * RouterOS API Client (PHP 8)
 * Mendukung RouterOS v6.x dan v7.x via protokol binary API Port 8728 (Plaintext)
 */

class RouterOS {
    private $socket = null;
    public bool $connected = false;
    public string $error = '';
    private int $timeout = 4;

    public function connect(string $host, string $username, string $password, int $port = 8728, int $timeout = 4): bool {
        $this->timeout = $timeout;
        $this->error = '';
        
        // 1. Cek ketersediaan fungsi socket di PHP
        if (!function_exists('fsockopen')) {
            $this->error = "Fungsi fsockopen() dinonaktifkan di php.ini server cPanel (disable_functions). Mohon aktifkan di cPanel -> MultiPHP INI Editor.";
            $this->connected = false;
            return false;
        }

        // 2. Buka koneksi socket ke MikroTik
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            $errLower = strtolower($errstr);
            if ($errno === 110 || str_contains($errLower, 'timed out') || str_contains($errLower, 'operation timed out')) {
                $this->error = "Koneksi Timeout ke {$host}:{$port}. Port 8728 diblokir oleh Firewall Outbound VPS cPanel (CSF TCP_OUT) atau ISP MikroTik.";
            } elseif ($errno === 111 || str_contains($errLower, 'refused')) {
                $this->error = "Koneksi Ditolak ({$host}:{$port}). Port 8728 di MikroTik tidak aktif atau dibatasi oleh /ip service / firewall.";
            } else {
                $this->error = "Gagal terhubung ke {$host}:{$port} [Err {$errno}: {$errstr}]. Periksa firewall outgoing cPanel atau koneksi.";
            }
            $this->connected = false;
            return false;
        }

        stream_set_timeout($socket, $timeout);
        $this->socket = $socket;

        // Login flow
        // Step 1: Coba login modern post-v6.43 / v7
        $this->writeWord('/login');
        $this->writeWord('=name=' . $username);
        $this->writeWord('=password=' . $password);
        $this->writeWord(''); // End of sentence

        $response = $this->readResponse();

        if (isset($response['!done'])) {
            // Jika router mengembalikan challenge (RouterOS v6.42 ke bawah)
            if (isset($response['!done']['ret'])) {
                $challenge = pack('H*', $response['!done']['ret']);
                $md5 = md5(chr(0) . $password . $challenge);

                $this->writeWord('/login');
                $this->writeWord('=name=' . $username);
                $this->writeWord('=response=00' . $md5);
                $this->writeWord('');

                $challengeResponse = $this->readResponse();
                if (!isset($challengeResponse['!done'])) {
                    $this->error = $challengeResponse['!trap']['message'] ?? 'Login ditolak oleh RouterOS';
                    $this->disconnect();
                    return false;
                }
            }
            $this->connected = true;
            return true;
        } elseif (isset($response['!trap'])) {
            $this->error = $response['!trap']['message'] ?? 'Login ditolak oleh RouterOS';
            $this->disconnect();
            return false;
        }

        $this->error = 'Respons login router tidak dikenali';
        $this->disconnect();
        return false;
    }

    public function disconnect(): void {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
        $this->connected = false;
    }

    /**
     * Kirim perintah ke RouterOS API
     * @param string $command Contoh: '/system/resource/print'
     * @param array $params Contoh: ['=enabled=yes'] atau ['.proplist=.id,name'] atau ['?chain=dstnat']
     */
    public function comm(string $command, array $params = []): array {
        if (!$this->connected || !$this->socket) {
            return [];
        }

        $this->writeWord($command);
        foreach ($params as $param) {
            $this->writeWord($param);
        }
        $this->writeWord(''); // End of sentence

        return $this->readAllResponses();
    }

    private function writeWord(string $word): void {
        $len = strlen($word);
        if ($len < 0x80) {
            $header = chr($len);
        } elseif ($len < 0x4000) {
            $len |= 0x8000;
            $header = chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
        } elseif ($len < 0x200000) {
            $len |= 0xC00000;
            $header = chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
        } elseif ($len < 0x10000000) {
            $len |= 0xE0000000;
            $header = chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
        } else {
            $header = chr(0xF0) . chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
        }

        @fwrite($this->socket, $header . $word);
    }

    private function readWord(): string {
        $byte1 = @fread($this->socket, 1);
        if ($byte1 === false || strlen($byte1) === 0) {
            return '';
        }

        $val = ord($byte1);
        $len = 0;

        if (($val & 0x80) === 0) {
            $len = $val;
        } elseif (($val & 0xC0) === 0x80) {
            $byte2 = ord(@fread($this->socket, 1));
            $len = (($val & 0x3F) << 8) + $byte2;
        } elseif (($val & 0xE0) === 0xC0) {
            $byte2 = ord(@fread($this->socket, 1));
            $byte3 = ord(@fread($this->socket, 1));
            $len = (($val & 0x1F) << 16) + ($byte2 << 8) + $byte3;
        } elseif (($val & 0xF0) === 0xE0) {
            $byte2 = ord(@fread($this->socket, 1));
            $byte3 = ord(@fread($this->socket, 1));
            $byte4 = ord(@fread($this->socket, 1));
            $len = (($val & 0x0F) << 24) + ($byte2 << 16) + ($byte3 << 8) + $byte4;
        } elseif (($val & 0xF8) === 0xF0) {
            $byte2 = ord(@fread($this->socket, 1));
            $byte3 = ord(@fread($this->socket, 1));
            $byte4 = ord(@fread($this->socket, 1));
            $byte5 = ord(@fread($this->socket, 1));
            $len = ($byte2 << 24) + ($byte3 << 16) + ($byte4 << 8) + $byte5;
        }

        if ($len === 0) {
            return '';
        }

        $word = '';
        $remaining = $len;
        while ($remaining > 0) {
            $chunk = @fread($this->socket, $remaining);
            if ($chunk === false || strlen($chunk) === 0) {
                break;
            }
            $word .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $word;
    }

    private function readResponse(): array {
        $response = [];
        $type = null;

        while (true) {
            $word = $this->readWord();
            if ($word === '') {
                break;
            }

            if ($type === null) {
                $type = $word;
                $response[$type] = [];
            } else {
                if (str_starts_with($word, '=')) {
                    $parts = explode('=', substr($word, 1), 2);
                    $response[$type][$parts[0]] = $parts[1] ?? '';
                } elseif (str_starts_with($word, '.tag=')) {
                    $response['.tag'] = substr($word, 5);
                }
            }
        }

        return $response;
    }

    private function readAllResponses(): array {
        $result = [];
        while (true) {
            $resp = $this->readResponse();
            if (empty($resp)) {
                break;
            }

            if (isset($resp['!re'])) {
                $result[] = $resp['!re'];
            } elseif (isset($resp['!done'])) {
                if (isset($resp['!done']['ret'])) {
                    $result['ret'] = $resp['!done']['ret'];
                }
                break;
            } elseif (isset($resp['!trap'])) {
                $result['!trap'] = $resp['!trap'];
                break;
            } elseif (isset($resp['!fatal'])) {
                $result['!fatal'] = $resp['!fatal'];
                $this->disconnect();
                break;
            }
        }
        return $result;
    }

    public function __destruct() {
        $this->disconnect();
    }
}

