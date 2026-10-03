<?php

class SimpleMailer {
    public string $host     = 'smtp.gmail.com';
    public int    $port     = 587;           
    public string $username = '';
    public string $password = '';
    public string $from     = '';
    public string $fromName = '';
    public string $encryption = 'tls';   

    private string $lastError = '';

    public function getLastError(): string { return $this->lastError; }

    public function send(string $to, string $toName, string $subject, string $body): bool {
        $this->lastError = '';

        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ]);

        if ($this->encryption === 'ssl') {
            $host = "ssl://{$this->host}";
        } else {
            $host = $this->host;
        }

        $sock = @stream_socket_client(
            "{$host}:{$this->port}",
            $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT, $ctx
        );

        if (!$sock) {
            $this->lastError = "Connection failed: $errstr ($errno)";
            return false;
        }

        stream_set_timeout($sock, 15);

        $read = function() use ($sock) {
            $data = '';
            while ($line = fgets($sock, 515)) {
                $data .= $line;
                if ($line[3] === ' ') break;  
            }
            return $data;
        };

        $write = function(string $cmd) use ($sock) {
            fputs($sock, $cmd . "\r\n");
        };

        $read(); 

        $write("EHLO crowddrop.local");
        $ehlo = $read();

        if ($this->encryption === 'tls') {
            $write("STARTTLS");
            $read(); 
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->lastError = "STARTTLS failed";
                fclose($sock);
                return false;
            }
            $write("EHLO crowddrop.local");
            $read();
        }

        $write("AUTH LOGIN");
        $read(); 
        $write(base64_encode($this->username));
        $read();
        $write(base64_encode($this->password));
        $auth = $read();
        if (strpos($auth, '235') === false) {
            $this->lastError = "Auth failed: $auth";
            fclose($sock);
            return false;
        }
        $write("MAIL FROM:<{$this->from}>");
        $read();
        $write("RCPT TO:<{$to}>");
        $rcpt = $read();
        if (strpos($rcpt, '250') === false) {
            $this->lastError = "RCPT TO rejected: $rcpt";
            fclose($sock);
            return false;
        }

        $write("DATA");
        $read(); 

        $date    = date('r');
        $fromEnc = $this->fromName ? "=?UTF-8?B?" . base64_encode($this->fromName) . "?=" : $this->from;
        $toEnc   = $toName ? "=?UTF-8?B?" . base64_encode($toName) . "?=" : $to;
        $subjEnc = "=?UTF-8?B?" . base64_encode($subject) . "?=";

        $message  = "Date: $date\r\n";
        $message .= "From: $fromEnc <{$this->from}>\r\n";
        $message .= "To: $toEnc <{$to}>\r\n";
        $message .= "Subject: $subjEnc\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "\r\n";
        $message .= chunk_split(base64_encode($body));
        $message .= "\r\n.";

        $write($message);
        $dataResp = $read();
        if (strpos($dataResp, '250') === false) {
            $this->lastError = "DATA rejected: $dataResp";
            fclose($sock);
            return false;
        }

        $write("QUIT");
        fclose($sock);
        return true;
    }
}
