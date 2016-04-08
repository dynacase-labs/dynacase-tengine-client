<?php
/*
 * @author Anakeen
 * @package FDL
*/
/**
 * Function to dialog with transformation server engine
 *
 * @author Anakeen
 * @version $Id: Class.TEClient.php,v 1.12 2007/08/14 09:39:33 eric Exp $
 * @package FDL
 */
/**
 */

namespace Dcp\TransformationEngine;

include_once ("WHAT/Lib.FileMime.php");

class ClientException extends \Exception
{
};

class ClientRequestException extends ClientException
{
};

class ClientResponseException extends ClientException
{
};

class ClientResponseIOException extends ClientResponseException
{
};

class ClientResponseFormatException extends ClientResponseException
{
};

class Client
{
    
    const TASK_STATE_BEGINNING = 'B'; // C/S start of transaction
    const TASK_STATE_TRANSFERRING = 'T'; // Data (file) transfer is in progress
    const TASK_STATE_ERROR = 'K'; // Job ends with error
    const TASK_STATE_SUCCESS = 'D'; // Job ends successfully
    const TASK_STATE_RECOVERED = 'R'; // Data recovered by client
    const TASK_STATE_PROCESSING = 'P'; // Engine is running
    const TASK_STATE_WAITING = 'W'; // Job registered, waiting to start engine
    const TASK_STATE_INTERRUPTED = 'I'; // Job was interrupted
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
    function __construct($host, $port)
    {
        $this->host = $host;
        $this->port = $port;
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
        $fp = stream_socket_client("tcp://$address:$service_port", $errno, $errstr, $timeout);
        
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
                if (preg_match('|<response[^>]*>(.*)</response>|i', $out, $match)) {
                    $err = $match[1];
                }
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
        $timeout = floatval(getParam("TE_TIMEOUT", 3));
        $fp = stream_socket_client("tcp://$address:$service_port", $errno, $errstr, $timeout);
        
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
        return $this->getAndLeaveTransformation($tid, $filename);
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
        $timeout = floatval(getParam("TE_TIMEOUT", 3));
        $fp = stream_socket_client("tcp://$address:$service_port", $errno, $errstr, $timeout);
        
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
     * Abort a transformation and delete associated files on the server
     * @param string $tid Task identification
     * @param string $filename the path where put the file (must be writeable)
     * @param array &$info transformation task info return "tid"=> ,"status"=> ,"comment=>
     *
     * @return string error message, if no error empty string
     */
    function abortTransformation($tid)
    {
        $err = "";
        /* Lit l'adresse IP du serveur de destination */
        $address = gethostbyname($this->host);
        $service_port = $this->port;
        /* Cree une socket TCP/IP. */
        //    echo "Essai de connexion à '$address' sur le port '$service_port'...\n";
        //    $result = socket_connect($socket, $address, $service_port);
        $timeout = floatval(getParam("TE_TIMEOUT", 3));
        $fp = stream_socket_client("tcp://$address:$service_port", $errno, $errstr, $timeout);
        
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
        $timeout = floatval(getParam("TE_TIMEOUT", 3));
        $sock = stream_socket_client("tcp://$saddr:$sport", $errno, $errstr, $timeout);
        if ($sock === false) {
            throw new ClientException(_("socket creation error") . " : $errstr ($errno)\n");
        }
        $msg = fgets($sock, 2048);
        if ($msg === false) {
            throw new ClientException(_("Handshake error"));
        }
        $msg = trim($msg);
        if ($msg != 'Continue') {
            throw new ClientException(_("Unexpected handshake message: %s") , $msg);
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
    private function _genericCommandWithErrResponse($cmd)
    {
        try {
            try {
                $sock = $this->connect();
            }
            catch(ClientException $e) {
                throw new ClientException(sprintf(_("Could not connect to TE server: %s") , $e->getMessage()));
            }
            $ret = $this->fwrite_stream($sock, $cmd);
            if ($ret != strlen($cmd)) {
                fclose($sock);
                throw new ClientRequestException(_("Could not send command to TE server."));
            }
            $msg = fgets($sock, 2048);
            if ($msg === false) {
                fclose($sock);
                throw new ClientResponseIOException(_("Could not read response from TE server."));
            }
            if (!preg_match('/<response.*\bstatus\s*=\s*"OK"/', $msg)) {
                if (preg_match('|<response[^>]*>(?P<err>.*)</response>|i', $msg, $m)) {
                    fclose($sock);
                    throw new ClientResponseFormatException($m['err']);
                }
                fclose($sock);
                throw new ClientResponseFormatException('unknown-error');
            }
            fclose($sock);
        }
        catch(ClientException $e) {
            return $e->getMessage();
        }
        return '';
    }
    private function _genericCommandWithJSONResponse($cmd, &$responseData)
    {
        try {
            $this->_genericCommandWithJSONResponse_ex($cmd, $responseData);
        }
        catch(ClientException $e) {
            return $e->getMessage();
        }
        return '';
    }
    private function _genericCommandWithJSONResponse_ex($cmd, &$responseData)
    {
        try {
            $sock = $this->connect();
        }
        catch(ClientException $e) {
            throw new ClientException(sprintf(_("Could not connect to TE server: %s") , $e->getMessage()));
        }
        $ret = $this->fwrite_stream($sock, $cmd);
        if ($ret != strlen($cmd)) {
            fclose($sock);
            throw new ClientRequestException(_("Could not send command to TE server."));
        }
        $msg = fgets($sock, 2048);
        if ($msg === false) {
            fclose($sock);
            throw new ClientResponseIOException(_("Could not read response from TE server."));
        }
        if (!preg_match('/<response.*\bstatus\s*=\s*"OK"/', $msg)) {
            if (preg_match('|<response[^>]*>(?P<err>.*)</response>|i', $msg, $m)) {
                fclose($sock);
                throw new ClientResponseFormatException($m['err']);
            }
            fclose($sock);
            throw new ClientResponseFormatException('unknown-error');
        }
        $size = 0;
        if (preg_match('/\bsize\s*=\s*"(?P<size>\d+)"/', $msg, $m)) {
            $size = $m['size'];
        }
        if ($size <= 0) {
            fclose($sock);
            throw new ClientResponseFormatException(sprintf(_("Invalid response size '%s'") , $size));
        }
        $data = $this->read_size($sock, $size);
        if ($data === false) {
            fclose($sock);
            throw new ClientResponseIOException(_("Could not read response from TE server."));
        }
        fclose($sock);
        $json = new \JSONCodec();
        $responseData = $json->decode($data, true);
        if (is_scalar($responseData)) {
            /* Return error message from TE server */
            throw new ClientResponseFormatException($responseData);
        }
        if (!is_array($responseData)) {
            throw new ClientResponseFormatException(sprintf(_("Returned data is not of array type (%s)") , gettype($responseData)));
        }
    }
    /**
     * Retrieve tasks
     * @param $tasks array which will hold the retrieved tasks
     * @param int $start pagination start (default '0')
     * @param int $length pagination length (default '0' for no limitation)
     * @param string $orderby the column to sort by (default '')
     * @param string $sort the sort order: '' (default for no ordering), 'asc' (ascending) or 'desc' (descending)
     * @param array $filter regex search filters: ex. array('col_1' => '^a[bc]')
     * @return string
     */
    public function retrieveTasks(&$tasks, $start = 0, $length = 0, $orderby = '', $sort = '', $filter = array())
    {
        $args = json_encode(array(
            "start" => $start,
            "length" => $length,
            "orderby" => $orderby,
            "sort" => $sort,
            "filter" => $filter
        ));
        $cmd = sprintf("INFO:TASKS\n<args type=\"application/json\" size=\"%d\"/>\n%s", strlen($args) , $args);
        return $this->_genericCommandWithJSONResponse($cmd, $tasks);
    }
    /**
     * Retrieve list of known engines from TE server
     * @param $engines array which will hold the returned engines
     * @return string error message on failure or empty string on success
     */
    public function retrieveEngines(&$engines)
    {
        return $this->_genericCommandWithJSONResponse("INFO:ENGINES\n", $engines);
    }
    /**
     * Retrieve history log for a specific task id
     * @param $histo array which will hold the history log
     * @param string $tid task id
     * @return string
     */
    public function retrieveTaskHisto(&$histo, $tid)
    {
        return $this->_genericCommandWithJSONResponse(sprintf("INFO:HISTO\n<task id=\"%s\"/>\n", $tid) , $histo);
    }
    /**
     * Retrive the list of all available selftests
     * @param $selftests array which will hold the available selftests
     * @return string error message on failure or empty string on success
     */
    public function retrieveSelftests(&$selftests)
    {
        return $this->_genericCommandWithJSONResponse("INFO:SELFTESTS\n", $selftests);
    }
    /**
     * Execute a single selftest
     * @param $result array which will hold the selftest result
     * @param string $selftestid selftest id
     * @return string
     */
    public function executeSelftest(&$result, $selftestid)
    {
        return $this->_genericCommandWithJSONResponse(sprintf("SELFTEST\n<selftest id=\"%s\"/>\n", $selftestid) , $result);
    }
    /**
     * Retrieve server's informations
     * @param $serverInfo array which will hold the server's informations
     * @param bool $extended true to request extended information, false for basic information
     * @return string error message on failure or empty string on success
     */
    public function retrieveServerInfo(&$serverInfo, $extended = false)
    {
        try {
            if ($extended) {
                $this->_genericCommandWithJSONResponse_ex("INFO:SERVER:EXTENDED\n", $serverInfo);
            } else {
                $this->_genericCommandWithJSONResponse_ex("INFO:SERVER\n", $serverInfo);
            }
        }
        catch(ClientResponseIOException $e) {
            $err = $e->getMessage();
            return sprintf(_("Server did not sent a valid response: server version might be incompatible.") , $err);
        }
        catch(ClientException $e) {
            return $e->getMessage();
        }
        return '';
    }
    /**
     * Purge tasks older than $maxdays days and with the given optional $status
     * @param string & $err the response message
     * @param int $maxdays
     * @param string $status
     * @return string client error message on failure or empty string on success
     */
    public function purgeTasks($maxdays = 0, $status = '')
    {
        $cmd = sprintf("PURGE\n<tasks maxdays=\"%s\" status=\"%s\" />\n", $maxdays, $status);
        return $this->_genericCommandWithErrResponse($cmd);
    }
    /**
     * Purge a single task given its identifier.
     * @param string $tid task's identifier
     * @return string client error message on failure or empty string on success
     */
    public function purgeTransformation($tid)
    {
        $cmd = sprintf("PURGE\n<tasks tid=\"%s\" />\n", $tid);
        return $this->_genericCommandWithErrResponse($cmd);
    }
}
