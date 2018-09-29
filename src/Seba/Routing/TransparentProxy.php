<?php
/*--------------------------------------------------------------/
| PROXY.PHP                                                     |
| Created By: Évelyne Lachance                                  |
| Contact: eslachance@gmail.com                                 |
| Modified By: Sebastiano Gallo                                 |
| Contact: sebalavoro@gmail.com                                 |
| Source: http://github.com/sebagallo/php-transparent-proxy 	|
| Description: This proxy does a POST or GET request from any   |
|         page to the defined URL                               |
/--------------------------------------------------------------*/

namespace Seba\Routing;

class TransparentProxy
{
    private $destinationURL;
    private $ip;
    private $port;

    public function __construct($url, $port = null)
    {
        $this->destinationURL = $url;
        $this->port = $port;
        $this->configureIp();
    }

    private function configureIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $this->ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $this->ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $this->ip = $_SERVER['REMOTE_ADDR'];
        } elseif (!empty($_SERVER['SERVER_ADDR'])) {
            $this->ip = $_SERVER['SERVER_ADDR'];
        } else {
            die('Error: the proxy only works in a http environment');
        }
    }

    public function makeRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == "GET") {
            $data = $_GET;
        } elseif ($method == "POST" && count($_POST) > 0) {
            $data = $_POST;
        } else {
            $data = file_get_contents('php://input');
        }
        $response = $this->proxy_request($this->destinationURL, $data, $method);
        $headerArray = explode("\r\n", $response['header']);
        $is_gzip = false;
        $is_chunked = false;
        foreach ($headerArray as $headerLine) {
            if ($headerLine == "Content-Encoding: gzip") {
                $is_gzip = true;
            } elseif ($headerLine == "Transfer-Encoding: chunked") {
                $is_chunked = true;
            }
        }
        $contents = $response['content'];
        if ($is_chunked) {
            $contents = $this->decode_chunked($contents);
        }
        if ($is_gzip) {
            $contents = gzdecode($contents);
        }
        return $contents;
    }

    private function proxy_request($url, $data, $method)
    {
        if ($method == "GET" || ($method == "POST" && count($_POST) > 0)) {
            $data = http_build_query($data);
            $data .= parse_url($url, PHP_URL_QUERY);
        }

        $datalength = strlen($data);

        $url = parse_url($url);

        $scheme = $url['scheme'];
        $host = $url['host'];

        if (!empty($url['path'])) {
            $path = $url['path'];
        } else {
            $path = "/";
        }

        if ($this->isHttpRequest($scheme)) {
            if (empty($this->port)) $this->setDefaultPort($scheme);
            $fp = fsockopen($host, $this->port, $errno, $errstr, 30);
        } else {
            die('Error: Only HTTP(s) request are supported !');
        }

        if ($fp) {
            if ($method == "POST") {
                fputs($fp, "POST $path HTTP/1.1\r\n");
            } else {
                fputs($fp, "GET $path?$data HTTP/1.1\r\n");
            }
            fputs($fp, "Host: $host\r\n");

            fputs($fp, "X-Forwarded-For: $this->ip\r\n");
            fputs($fp, "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7\r\n");

            $requestHeaders = apache_request_headers();
            while ((list($header, $value) = each($requestHeaders))) {
                if ($header == "Content-Length") {
                    fputs($fp, "Content-Length: $datalength\r\n");
                } else if ($header !== "Connection" && $header !== "Host" && $header !== "Content-length") {
                    fputs($fp, "$header: $value\r\n");
                }
            }
            fputs($fp, "Connection: close\r\n\r\n");
            fputs($fp, $data);

            $result = '';
            while (!feof($fp)) {
                $result .= fgets($fp, 128);
            }
        } else {
            return array(
                'status' => 'err',
                'error' => "$errstr ($errno)"
            );
        }

        fclose($fp);

        $result = explode("\r\n\r\n", $result, 2);
        $header = isset($result[0]) ? $result[0] : '';
        $content = isset($result[1]) ? $result[1] : '';

        return array(
            'status' => 'ok',
            'header' => $header,
            'content' => $content
        );
    }

    private function isHttpRequest($scheme)
    {
        return preg_match('/^https?$/', $scheme) === 1;
    }

    private function setDefaultPort($scheme)
    {
        if ($scheme == 'http') $this->port = 80;
        elseif ($scheme == 'https') $this->port = 443;
    }

    private function decode_chunked($str)
    {
        for ($res = ''; !empty($str); $str = trim($str)) {
            $pos = strpos($str, "\r\n");
            $len = hexdec(substr($str, 0, $pos));
            $res .= substr($str, $pos + 2, $len);
            $str = substr($str, $pos + 2 + $len);
        }
        return $res;
    }

}

if (!function_exists('apache_request_headers')) {
    function apache_request_headers()
    {
        $headers = array();
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

if (!function_exists('gzdecode')) {
    function gzdecode($data)
    {
        return gzinflate(substr($data, 10, -8));
    }
}
