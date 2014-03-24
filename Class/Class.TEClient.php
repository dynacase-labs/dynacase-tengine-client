<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FDL
*/
/**
 * Function to dialog with transformation server engine
 *
 * @author Anakeen
 * @version $Id: Class.TEClient.php,v 1.12 2007/08/14 09:39:33 eric Exp $
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 * @package FDL
 */
/**
 */

namespace Dcp\TransformationEngine;

include_once ("WHAT/Lib.FileMime.php");

class ClientException extends \Exception
{
};

class Client
{
    const error_connect = - 2;
    const error_noengine = - 3;
    const error_sendfile = - 4;
    const error_emptyfile = - 5;
    const error_convert = - 1;
    const status_inprogress = 2;
    const status_waiting = 3;
    const status_done = 1;
    /**
     * host name of the transformation engine server
     * @private string
     */
    private $host = 'localhost';
    /**
     * port number of the transformation engine server
     * @private int
     */
    private $port = 51968;
    /**
     * initialize host and port
     * @param string $host host name
     * @param int $port port number
     *
     */
    function __construct($host = "localhost", $port = 51968)
    {
        if ($host != "") $this->host = $host;
        if ($port > 0) $this->port = $port;
    }
    /**
     * send a request to do a transformation
     * @param string $te_name Engine name
     * @param string $fkey foreign key
     * @param string $filename the path where is the original file
     * @param string $callback url to activate after transformation is done
     * @param array &$info transformation task info return "tid"=> ,"status"=> ,"comment=>
     *
     * @return string error message, if no error empty string
     */
    function sendTransformation($te_name, $fkey, $filename, $callback, &$info)
    {
        $err = "";
        
        clearstatcache(); // to reset filesize
        if (!file_exists($filename)) {
            $err = sprintf("file %s not found", $filename);
            $info = array(
                "status" => self::error_emptyfile
            );
            return $err;
        }
        $size = filesize($filename);
        if ($size <= 0) {
            $err = _("empty file");
            $info = array(
                "status" => self::error_emptyfile
            );
            return $err;
        }
        /* Lit l'adresse IP du serveur de destination */
        $address = gethostbyname($this->host);
        $service_port = $this->port;
        /* Cree une socket TCP/IP. */
        //  echo "Essai de connexion à '$address' sur le port '$service_port'...\n";
        //    $result = socket_connect($socket, $address, $service_port);
        $timeout = floatval(getParam("TE_TIMEOUT", 3));
        $fp = @stream_socket_client("tcp://$address:$service_port", $errno, $errstr, $timeout);
        
        if (!$fp) {
            $err = _("socket creation error") . " : $errstr ($errno)\n";
            $info = array(
                "status" => self::error_connect
            );
            return $err;
        }
        /*
         * TE server should speak first with a "Continue" response
        */
        $out = fgets($fp, 2048);
        if ($out === false) {
            $err = sprintf(_("Error sending file") . " (error reading expected 'Continue' response)");
            $info = array(
                "status" => self::error_sendfile
            );
            return $err;
        }
        if (trim($out) != 'Continue') {
            $err = sprintf(_("Error sending file") . " (unexpected response from server: [%s])(server at %s:%s might not be a TE server?)", $out, $address, $service_port);
            $info = array(
                "status" => self::error_sendfile
            );
            return $err;
        }
        /*
         * Send CONVERT request
        */
        $basename = str_replace('"', '_', basename($filename));
        $mime = getSysMimeFile($filename, $basename);
        
        $in = "CONVERT\n";
        fputs($fp, $in);
        $in = "<TE name=\"$te_name\" fkey=\"$fkey\" fname=\"$basename\" size=\"$size\" mime=\"$mime\" callback=\"$callback\"/>\n";
        fputs($fp, $in);
        /*
         * Receive CONVERT response
        */
        $out = trim(fgets($fp));
        $status = "KO";
        if (preg_match('/status=[ ]*"([^"]*)"/i', $out, $match)) {
            $status = $match[1];
        }
        if ($status == 'OK') {
            //echo "Envoi du fichier $filename ...";
            if (file_exists($filename)) {
                $handle = @fopen($filename, "r");
                if ($handle) {
                    while (!feof($handle)) {
                        $buffer = fread($handle, 2048);
                        $cout = fwrite($fp, $buffer, strlen($buffer));
                    }
                    fclose($handle);
                }
                
                fflush($fp);
                //echo "OK.\n";
                // echo "Lire la réponse : \n\n";
                $out = trim(fgets($fp));
                if (preg_match('/status=[ ]*"([^"]*)"/i', $out, $match)) {
                    $status = $match[1];
                }
                $outmsg = '';
                if (preg_match('|<response[^>]*>(.*)</response>|i', $out, $match)) {
                    $outmsg = $match[1];
                }
                //echo "Response [$status]\n";
                //echo "Message [$outmsg]\n";
                if ($status == "OK") {
                    $tid = 0;
                    if (preg_match('/ id=[ ]*"([^"]*)"/i', $outmsg, $match)) {
                        $tid = $match[1];
                    }
                    if (preg_match('/status=[ ]*"([^"]*)"/i', $outmsg, $match)) {
                        $status = $match[1];
                    }
                    $comment = '';
                    if (preg_match('|<comment>(.*)</comment>|i', $outmsg, $match)) {
                        $comment = $match[1];
                    }
                    $info = array(
                        "tid" => $tid,
                        "status" => $status,
                        "comment" => $comment
                    );
                } else {
                    $err = " [$outmsg]";
                }
            }
        } else {
            $taskerr = '-';
            if (preg_match('|<comment>(.*)</comment>|i', $out, $match)) {
                $info = array(
                    "status" => self::error_noengine
                );
                $err = $match[1];
            } else {
                $err = _("Error sending file");
                $info = array(
                    "status" => self::error_sendfile
                );
            }
        }
        //echo "Fermeture de la socket...";
        fclose($fp);
        
        return $err;
    }
    /**
     * send a request to get information about a task
     * @param int $tid_task identifier
     * @param array &$info transformation task info return "tid"=> ,"status"=> ,"comment=>
     *
     * @return string error message, if no error empty string
     */
    function getInfo($tid, &$info)
    {
        $err = "";
        /* Lit l'adresse IP du serveur de destination */
        $address = gethostbyname($this->host);
        $service_port = $this->port;
        /* Cree une socket TCP/IP. */
        //    echo "Essai de connexion à '$address' sur le port '$service_port'...\n";
        //    $result = socket_connect($socket, $address, $service_port);
        $fp = stream_socket_client("tcp://$address:$service_port", $errno, $errstr, 30);
        
        if (!$fp) {
            $err = _("socket creation error") . " : $errstr ($errno)\n";
        }
        
        if ($err == "") {
            
            $in = "INFO\n";
            //echo "Envoi de la commande $in ...";
            fputs($fp, $in);
            
            $out = trim(fgets($fp, 2048));
            //echo "[$out].\n";
            if ($out == "Continue") {
                
                $in = "<TASK id=\"$tid\" />\n";
                //echo "Envoi du header $in ...";
                fputs($fp, $in);
                
                $out = trim(fgets($fp));
                $status = '';
                if (preg_match('/status=[ ]*"([^"]*)"/i', $out, $match)) {
                    $status = $match[1];
                }
                
                if ($status == "OK") {
                    //echo "<br>Response <b>$out</b>";
                    if (preg_match('|<task[^>]*>(.*)</task>|i', $out, $match)) {
                        $body = $match[1];
                        //	echo "Response $body";
                        if (preg_match_all('|<[^>]+>(.*)</([^>]+)>|U', $body, $reg, PREG_SET_ORDER)) {
                            
                            foreach ($reg as $v) {
                                $info[$v[2]] = $v[1];
                            }
                        }
                    }
                } else {
                    $msg = "";
                    if (preg_match('|<response[^>]*>(.*)</response>|i', $out, $match)) {
                        $msg = $match[1];
                    }
                    $err = $status . " [$msg]";
                }
            }
            //echo "Fermeture de la socket...";
            fclose($fp);
        }
        
        return $err;
    }
    /**
     * send a request to retrieve a transformation and to erase task from server
     * the status must be D (Done) or K (Done but errors).
     * @param string $tid Task identification
     * @param string $filename the path where put the file (must be writeable)
     *
     * @return string error message, if no error empty string
     */
    function getTransformation($tid, $filename)
    {
        $err = $this->getAndLeaveTransformation($tid, $filename);
        $this->eraseTransformation($tid);
    }
    /**
     * send a request for retrieve a transformation
     * the status must be D (Done) or K (Done but errors).
     * all working files are stayed into the server : be carreful to clean it after (use ::eraseTransformation)
     * @param string $tid Task identification
     * @param string $filename the path where put the file (must be writeable)
     *
     * @return string error message, if no error empty string
     */
    function getAndLeaveTransformation($tid, $filename)
    {
        
        $err = "";
        
        $handle = @fopen($filename, "w");
        if (!$handle) {
            $err = sprintf("cannot open file <%s> in write mode", $filename);
            return $err;
        }
        /* Lit l'adresse IP du serveur de destination */
        $address = gethostbyname($this->host);
        $service_port = $this->port;
        /* Cree une socket TCP/IP. */
        //echo "Essai de connexion à '$address' sur le port '$service_port'...\n";
        //    $result = socket_connect($socket, $address, $service_port);
        $fp = stream_socket_client("tcp://$address:$service_port", $errno, $errstr, 30);
        
        if (!$fp) {
            $err = _("socket creation error") . " : $errstr ($errno)\n";
        }
        
        if ($err == "") {
            $in = "GET\n";
            //echo "Envoi de la commande $in ...";
            fputs($fp, $in);
            
            $out = trim(fgets($fp, 2048));
            //echo "[$out].\n";
            if ($out == "Continue") {
                
                $in = "<task id=\"$tid\" />\n";
                //echo "Envoi du header $in ...";
                fputs($fp, $in);
                //echo "Recept du file size ...";
                $out = trim(fgets($fp, 2048));
                //echo "[$out]\n";
                $status = '';
                if (preg_match('/status=[ ]*"([^"]*)"/i', $out, $match)) {
                    $status = $match[1];
                }
                if ($status == "OK") {
                    $size = 0;
                    if (preg_match('/size=[ ]*"([^"]*)"/i', $out, $match)) {
                        $size = $match[1];
                    }
                    //echo "Recept du fichier $filename ...";
                    if ($handle) {
                        $trbytes = 0;
                        $orig_size = $size;
                        do {
                            if ($size >= 2048) {
                                $rsize = 2048;
                            } else {
                                $rsize = $size;
                            }
                            if ($rsize > 0) {
                                $buf = fread($fp, $rsize);
                                if ($buf === false || $buf === "") {
                                    $err = sprintf("error reading from msgsock (%s/%s bytes transferred))", $trbytes, $orig_size);
                                    break;
                                }
                                
                                $l = strlen($buf);
                                $trbytes+= $l;
                                $size-= $l;
                                $wb = fwrite($handle, $buf);
                            }
                            //echo "file:$l []";
                            
                        } while ($size > 0);
                        
                        fclose($handle);
                    }
                    //echo "Wroted  $filename\n.";
                    // echo "Lire la réponse : \n\n";
                    $out = trim(fgets($fp, 2048));
                    if (preg_match('/status=[ ]*"([^"]*)"/i', $out, $match)) {
                        $status = $match[1];
                    }
                    if ($status != "OK") {
                        $msg = "";
                        if (preg_match('|<response[^>]*>(.*)</response>|i', $out, $match)) {
                            $msg = $match[1];
                        }
                        $err = "$status:$msg";
                    }
                } else {
                    // status not OK
                    $msg = "";
                    if (preg_match('|<response[^>]*>(.*)</response>|i', $out, $match)) {
                        $msg = $match[1];
                    }
                    $err = "$status:$msg";
                }
            }
        }
        //echo "Fermeture de la socket...";
        fclose($fp);
        return $err;
    }
    /**
     * erase transformation
     * delete associated files in the server engine
     * @param string $tid Task identification
     * @param string $filename the path where put the file (must be writeable)
     * @param array &$info transformation task info return "tid"=> ,"status"=> ,"comment=>
     *
     * @return string error message, if no error empty string
     */
    function eraseTransformation($tid)
    {
        $err = "";
        /* Lit l'adresse IP du serveur de destination */
        $address = gethostbyname($this->host);
        $service_port = $this->port;
        /* Cree une socket TCP/IP. */
        //    echo "Essai de connexion à '$address' sur le port '$service_port'...\n";
        //    $result = socket_connect($socket, $address, $service_port);
        $fp = stream_socket_client("tcp://$address:$service_port", $errno, $errstr, 30);
        
        if (!$fp) {
            $err = _("socket creation error") . " : $errstr ($errno)\n";
        }
        
        if ($err == "") {
            
            $in = "ABORT\n";
            //echo "Envoi de la commande $in ...";
            fputs($fp, $in);
            
            $out = trim(fgets($fp, 2048));
            //echo "[$out].\n";
            if ($out == "Continue") {
                
                $in = "<TASK id=\"$tid\" />\n";
                //echo "Envoi du header $in ...";
                fputs($fp, $in);
                
                $out = trim(fgets($fp));
                $status = '';
                if (preg_match('/status=[ ]*"([^"]*)"/i', $out, $match)) {
                    $status = $match[1];
                }
                if ($status == "OK") {
                    //echo "<br>Response <b>$out</b>";
                    if (preg_match('|<task[^>]*>(.*)</task>|i', $out, $match)) {
                        $body = $match[1];
                        //	echo "Response $body";
                        if (preg_match_all('|<[^>]+>(.*)</([^>]+)>|U', $body, $reg, PREG_SET_ORDER)) {
                            
                            foreach ($reg as $v) {
                                $info[$v[2]] = $v[1];
                            }
                        }
                    }
                } else {
                    $msg = "";
                    if (preg_match('|<response[^>]*>(.*)</response>|i', $out, $match)) {
                        $msg = $match[1];
                    }
                    $err = $status . " [$msg]";
                }
            }
            //echo "Fermeture de la socket...";
            fclose($fp);
        }
        
        return $err;
    }
    /**
     * Establish a new connection to the TE server
     * @return resource
     * @throws \Exception
     */
    private function connect()
    {
        $saddr = gethostbyname($this->host);
        $sport = $this->port;
        $sock = stream_socket_client("tcp://$saddr:$sport", $errno, $errstr, 30);
        if ($sock === false) {
            throw new ClientException(_("socket creation error") . " : $errstr ($errno)\n");
        }
        $msg = fgets($sock, 2048);
        if ($msg === false) {
            throw new ClientException(_("Handshake error"));
        }
        $msg = trim($msg);
        if ($msg != 'Continue') {
            throw new ClientException(_("Unexpected handshake message: %s", $msg));
        }
        return $sock;
    }
    /**
     * Write data to the given socket file descriptor taking care of the fact that
     * writing to a network stream may end before the whole string is written.
     * @param $fp
     * @param $string
     * @return int
     */
    private function fwrite_stream($fp, $string)
    {
        for ($written = 0; $written < strlen($string); $written+= $fwrite) {
            $fwrite = fwrite($fp, substr($string, $written));
            if ($fwrite === false) {
                return $written;
            }
        }
        return $written;
    }
    /**
     * Read a specific number of bytes from the given socket file descriptor.
     * @param $fp
     * @param $size
     * @return bool|string the data or bool(false) on error
     */
    private function read_size($fp, $size)
    {
        $buf = '';
        while ($size > 0) {
            if ($size >= 2048) {
                $rsize = 2048;
            } else {
                $rsize = $size;
            }
            $data = fread($fp, $rsize);
            if ($data === false || $data === "") {
                return false;
            }
            $size-= strlen($data);
            $buf.= $data;
        }
        return $buf;
    }
    /**
     * Read all data till end-of-file from the given socket file descriptor.
     * @param $fp
     * @return bool|string the data or bool(false) on error
     */
    private function read_eof($fp)
    {
        $buf = '';
        while (!feof($fp)) {
            if (($data = fread($fp, 2048)) === false) {
                return false;
            }
            $buf.= $data;
        }
        return $buf;
    }
    /**
     * Retrieve list of known engines from TE server
     * @param $engines array which will hold de returned engines
     * @return string error message on failure or empty string on success
     */
    public function retrieveEngines(&$engines)
    {
        $engines = array();
        try {
            $sock = $this->connect();
        }
        catch(ClientException $e) {
            return $e->getMessage();
        }
        $cmd = "INFO:ENGINES\n";
        $ret = $this->fwrite_stream($sock, $cmd);
        if ($ret != strlen($cmd)) {
            return _("Error sending command to TE server");
        }
        $msg = fgets($sock, 2048);
        if ($msg === false) {
            return _("Error reading content from server");
        }
        if (!preg_match('/<response.*\bstatus\s*=\s*"OK"/', $msg)) {
            if (preg_match('|<response[^>]*>(?P<err>.*)</response>|i', $msg, $m)) {
                return $m['err'];
            }
            return 'unknown-error';
        }
        $size = 0;
        if (preg_match('/\bsize\s*=\s*"(?P<size>\d+)"/', $msg, $m)) {
            $size = $m['size'];
        }
        if ($size <= 0) {
            return sprintf(_("Invalid response size '%s'") , $size);
        }
        $data = $this->read_size($sock, $size);
        if ($data === false) {
            return _("Error reading content from server");
        }
        fclose($sock);
        $json = new \JSONCodec();
        try {
            $engines = $json->decode($data, true);
            if (is_scalar($engines)) {
                /* Return error message from TE server */
                return $engines;
            }
            if (!is_array($engines)) {
                throw new ClientException(sprintf(_("Returned data is not of array type (%s)") , gettype($engines)));
            }
        }
        catch(ClientException $e) {
            return sprintf(_("Malformed JSON response: %s") , $e->getMessage());
        }
        return '';
    }
    public function retrieveTasks(&$tasks, $start = 0, $length = 0, $orderby = '', $sort = '', $filter = array())
    {
        try {
            $sock = $this->connect();
            $args = json_encode(array(
                "start" => $start,
                "length" => $length,
                "orderby" => $orderby,
                "sort" => $sort,
                "filter" => $filter
            ));
            $cmd = sprintf("INFO:TASKS\n<args type=\"application/json\" size=\"%d\"/>\n%s", strlen($args) , $args);
            $ret = $this->fwrite_stream($sock, $cmd);
            if ($ret != strlen($cmd)) {
                throw new ClientException(_("Error sending command to TE server"));
            }
            $msg = fgets($sock, 2048);
            if ($msg === false) {
                return _("Error reading content from server");
            }
            if (!preg_match('/<response.*\bstatus\s*=\s*"OK"/', $msg)) {
                if (preg_match('|<response[^>]*>(?P<err>.*)</response>|i', $msg, $m)) {
                    throw new ClientException($m['err']);
                }
                throw new ClientException('unknown-error');
            }
            $size = 0;
            if (preg_match('/\bsize\s*=\s*"(?P<size>\d+)"/', $msg, $m)) {
                $size = $m['size'];
            }
            if ($size <= 0) {
                return sprintf(_("Invalid response size '%s'") , $size);
            }
            $data = $this->read_size($sock, $size);
            if ($data === false) {
                return _("Error reading content from server");
            }
            fclose($sock);
            $json = new \JSONCodec();
            $tasks = $json->decode($data, true);
            if (is_scalar($tasks)) {
                /* Return error message from TE server */
                throw new ClientException($tasks);
            }
            if (!is_array($tasks)) {
                throw new ClientException(sprintf(_("Returned data is not of array type (%s)") , gettype($tasks)));
            }
        }
        catch(ClientException $e) {
            return $e->getMessage();
        }
        return '';
    }
    public function retrieveTaskHisto(&$histo, $tid)
    {
        try {
            $sock = $this->connect();
            $cmd = sprintf("INFO:HISTO\n<task id=\"%s\"/>\n", $tid);
            $ret = $this->fwrite_stream($sock, $cmd);
            if ($ret != strlen($cmd)) {
                throw new ClientException(_("Error sending command to TE server"));
            }
            $msg = fgets($sock, 2048);
            if ($msg === false) {
                return _("Error reading content from server");
            }
            if (!preg_match('/<response.*\bstatus\s*=\s*"OK"/', $msg)) {
                if (preg_match('|<response[^>]*>(?P<err>.*)</response>|i', $msg, $m)) {
                    throw new ClientException($m['err']);
                }
                throw new ClientException('unknown-error');
            }
            $size = 0;
            if (preg_match('/\bsize\s*=\s*"(?P<size>\d+)"/', $msg, $m)) {
                $size = $m['size'];
            }
            if ($size <= 0) {
                return sprintf(_("Invalid response size '%s'") , $size);
            }
            $data = $this->read_size($sock, $size);
            if ($data === false) {
                return _("Error reading content from server");
            }
            fclose($sock);
            $json = new \JSONCodec();
            $histo = $json->decode($data, true);
            if (is_scalar($histo)) {
                /* Return error message from TE server */
                throw new ClientException($histo);
            }
            if (!is_array($histo)) {
                throw new ClientException(sprintf(_("Returned data is not of array type (%s)") , gettype($histo)));
            }
        }
        catch(ClientException $e) {
            return $e->getMessage();
        }
        return '';
    }
    private function _genericCommandWithErrResponse($cmd) {
        $err = '';
        $sock = false;
        try {
            $sock = $this->connect();
            $ret = $this->fwrite_stream($sock, $cmd);
            if ($ret != strlen($cmd)) {
                fclose($sock);
                throw new ClientException(_("Error sending command to TE server"));
            }
            $msg = fgets($sock, 2048);
            if ($msg === false) {
                return _("Error reading content from server");
            }
            if (!preg_match('/<response.*\bstatus\s*=\s*"OK"/', $msg)) {
                if (preg_match('|<response[^>]*>(?P<err>.*)</response>|i', $msg, $m)) {
                    throw new ClientException($m['err']);
                }
                throw new ClientException('unknown-error');
            }
        }
        catch(ClientException $e) {
            $err = $e->getMessage();
        }
        if ($sock !== false) {
            fclose($sock);
        }
        return $err;
    }
    private function _genericCommandWithJSONResponse($cmd, &$responseData)
    {
        try {
            $sock = $this->connect();
            $ret = $this->fwrite_stream($sock, $cmd);
            if ($ret != strlen($cmd)) {
                throw new ClientException(_("Error sending command to TE server"));
            }
            $msg = fgets($sock, 2048);
            if ($msg === false) {
                return _("Error reading content from server");
            }
            if (!preg_match('/<response.*\bstatus\s*=\s*"OK"/', $msg)) {
                if (preg_match('|<response[^>]*>(?P<err>.*)</response>|i', $msg, $m)) {
                    throw new ClientException($m['err']);
                }
                throw new ClientException('unknown-error');
            }
            $size = 0;
            if (preg_match('/\bsize\s*=\s*"(?P<size>\d+)"/', $msg, $m)) {
                $size = $m['size'];
            }
            if ($size <= 0) {
                return sprintf(_("Invalid response size '%s'") , $size);
            }
            $data = $this->read_size($sock, $size);
            if ($data === false) {
                return _("Error reading content from server");
            }
            fclose($sock);
            $json = new \JSONCodec();
            $responseData = $json->decode($data, true);
            if (is_scalar($responseData)) {
                /* Return error message from TE server */
                throw new ClientException($responseData);
            }
            if (!is_array($responseData)) {
                throw new ClientException(sprintf(_("Returned data is not of array type (%s)") , gettype($responseData)));
            }
        }
        catch(ClientException $e) {
            return $e->getMessage();
        }
        return '';
    }
    public function retrieveSelftests(&$selftests)
    {
        return $this->_genericCommandWithJSONResponse("INFO:SELFTESTS\n", $selftests);
    }
    public function executeSelftest(&$result, $selftestid)
    {
        return $this->_genericCommandWithJSONResponse(sprintf("SELFTEST\n<selftest id=\"%s\"/>\n", $selftestid) , $result);
    }
    public function retrieveServerInfo(&$serverInfo, $extended = false)
    {
        if ($extended) {
            return $this->_genericCommandWithJSONResponse("INFO:SERVER:EXTENDED\n", $serverInfo);
        }
        return $this->_genericCommandWithJSONResponse("INFO:SERVER\n", $serverInfo);
    }

    /**
     * Purge tasks older than $maxdays days and with the given optional $status
     * @param string & $err the response message
     * @param int $maxdays
     * @param string $status
     * @return string client error message on failure or empty string on success
     */
    public function purgeTasks($maxdays = 0, $status = '') {
        $cmd = sprintf("PURGE\n<tasks maxdays=\"%s\" status=\"%s\" />\n", $maxdays, $status);
        return $this->_genericCommandWithErrResponse($cmd);
    }
}
