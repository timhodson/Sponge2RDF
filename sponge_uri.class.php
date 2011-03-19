<?php
/** 
 * SpongeUri class
 * 
 * This class will parse a URI and provide a new URI that is for either a
 * thing or a document. It makes the following assumptions:
 * - The first path element is one of id|doc
 * - The last path element is a key with optional file extension
 * - content type or extension default to html if not set.
 * 
 * @author tim@timhodson.com
 */

class SpongeUri  {

    private $path ;
    private $base_uri ;
    private $thing_uri ;
    private $doc_uri ;

    /**
     * Constructor
     * 
     * Parses the given uri and makes some good guesses about it
     * 
     * @param String $baseuri
     * @param String $uri 
     */
    public function __construct($baseuri, $uri) {
        
        $this->base_uri = $baseuri ;

        // @todo DEAL WITH SCRIPT INJECTION ???
        $this->debug_print($uri, "Request URI");

        $parts = explode("/", $uri);
        $this->debug_print($parts, "Parts");

        /* This is generic will explode the path and
         * create a thing_uri and doc_uri if the path had 'id' as the first container.
         */
        if ($parts[1] == 'id') {
            $this->path['is_id'] = true;
            $this->path['is_doc'] = false;

            $last = array_pop($parts);
            if (preg_match("[.]", $last)) {
                $i = explode(".", $last);
                $this->path['key'] = $i[0];
                $this->path['extension'] = $i[1];
                $this->path['is_id'] = true;
                array_push($parts, $i[0]);
                $this->path['thing_uri'] = implode("/", $parts);
                $parts[1] = 'doc';
                $this->path['doc_uri'] = implode("/", $parts);
                if ($i[1] != '')
                    $this->path['doc_uri'] . "." . $this->path['extension'];
            }else {
                $this->path['thing_uri'] = implode("/", $parts);
            }
        /*
         * If the incoming path is doc, then process the path
         */
        } else if ($parts[1] == 'doc') {
            $this->path['is_doc'] = true;
            $this->path['is_id'] = false;
            $this->path['doc_uri'] = implode("/", $parts);

            // get the final part of the path as the id and extension if present
            $last = array_pop($parts);

            // get the rest of the path
            foreach($parts as $k => $v){
                if($k >= 2){ // skip the first and second keys (0,1,2..).
                    $this->path[$k] = $v ;
                }
            }

            // get the key and extension
            if (preg_match("/[.]/",$last)) {
                $i = explode(".", $last);
                $this->path['key'] = $i[0];
                $this->path['extension'] = $i[1];
                array_push($parts, $i[0]);
            }else{
                $this->path['key'] = $last;
                $this->path['extension'] = '';
                array_push($parts, $last);
            }

            $parts[1] = 'id';
            $this->path['thing_uri'] = implode("/", $parts);
        } else {
            if (SPONGE_SEND_RESPONSE)
                $res = new http_response('404', '', '');
        }


        $this->thing_uri = $this->base_uri . $this->path['thing_uri'];
        $this->doc_uri = $this->base_uri . $this->path['doc_uri'];

        $this->debug_print($this->path, "Path");
    }

    /**
     * 
     * @param bool $absolute
     * @return string A relative (or absolute) path for the thing
     */
    public function get_thing_uri($absolute=false){
        if(!$absolute) return $this->path['thing_uri'];
        return $this->thing_uri;
    }
    
    /**
     * 
     * @param bool $absolute
     * @return string A relative (or absolute) path for the document about the thing
     */
    public function get_doc_uri($absolute=false){
        if(!$absolute) return $this->path['doc_uri'];
        return $this->doc_uri;
    }
    /**
     * @return string Get the base of the URI
     */
    public function get_base_uri() {
        return $this->base_uri;
    }
    /**
     * @return string The extension used
     */
    public function get_extension(){
        return $this->path['extension'];
    }
    /**
     *
     * @return string The key in the URI
     */
    public function get_key(){
        return $this->path['key'];
    }
    /**
     *
     * @return array The rest of the containers in the path.
     */
    public function get_containers(){
        $out = array();
        foreach ($this->path as $k => $v){
            if(is_numeric($k)){
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /**
     *
     * @param string $param
     * @return bool whether $param is or is not the first container
     */
    public function is_first_container($param) {
        if($this->path[2] == $param) return true ;
        return false;
    }
    /**
     *
     * @param string $param
     * @return bool whether $param is or is not the second container
     */
    public function is_second_container($param) {
        if($this->path[3] == $param) return true ;
        return false;
    }
    /**
     *
     * @param string $param
     * @return bool whether $param is or is not the third container
     */
    public function is_third_container($param) {
        if($this->path[4] == $param) return true ;
        return false;
    }
    /**
     *
     * @param string $param
     * @return bool whether $param is or is not the fourth container
     */
    public function is_fourth_container($param) {
        if($this->path[5] == $param) return true ;
        return false;
    }
    /**
     *
     * @return bool True if the URI is a thing URI
     */
    public function is_thing(){
        if ($this->path['is_id']){
            return true;
        }else{
            return false;
        }
    }
    /**
     *
     * @return bool True if the URI is a doc URI
     */
    public function is_doc(){
        if ($this->path['is_doc']){
            return true;
        }else{
            return false;
        }
    }
    /**
     *
     * @return string The Content-Type for use in a HTTP Header.
     */
    public function get_content_type() {
        switch ($this->get_extension()) {
            case 'html':
                return 'text/html';
                break;
            case 'json':
                return 'text/json';
                break;
            case 'rdf' :
                return 'application/rdf+xml';
                break;
            case 'ttl' :
                return 'text/turtle';
                break;
            case 'nt' :
                return 'text/ntriples';
                break;
            default:
                return 'text/html';
                break;
        }
    }

    /**
     *
     *
     * Print debug information to browser.
     *
     * @param mixed $var A variable to display to the screen
     * @param string $title The title of the display (uses a h2 element)
     * @param bool $flag Override the SPONGE_DEBUG constant
     */
    private function debug_print($var, $title='', $flag = 0) {
        if (SPONGE_DEBUG || $flag) {
            echo "<h2>" . $title . "</h2>";
            echo "<pre>";
            print_r($var);
            echo "</pre>";
        }
    }
}
?>
