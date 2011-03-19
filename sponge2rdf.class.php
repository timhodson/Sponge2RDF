<?php

/**
 *  Script to get data from somewhere, soak it up and create some RDF
 *  supported path elements should be setup in advance
 *      http://...com/id/colour/00ff00
 *      http://...com/id/compound/identifer
 * It is assumed the path begins with either doc or id
 *  for information and non-information resources respectively
 *
 * @author Tim Hodson tim@timhodson.com
 */


// use Moriarty code to do stuff
require_once MORIARTY_DIR . 'moriarty.inc.php';
require_once MORIARTY_DIR . 'store.class.php';
require_once MORIARTY_DIR . 'credentials.class.php';

require_once 'sponge_response.class.php';
require_once 'sponge_uri.class.php';
require_once 'sponge_builder.interface.php';


/*
 * Pseudo Code
 *
 * recieve request
 * match path e.g. /colour/{hex}
 * if not available in a Talis Platform Store
 *  generate data   
 *  serialize to requested format e.g. turtle
 *  post to Talis Platform Store
 * else
 * return from Talis Platform Store
 *
 */
class Sponge2Rdf implements SpongeBuilder {

    public $uri;
    public $graph;

    public function __construct() {

        if (SPONGE_SEND_RESPONSE)
            $this->parse_path();
        if (SPONGE_SEND_RESPONSE)
            $this->build_response();
        //return $this;
    }

    /**
     * 
     * @param string $uri The URI to parse if not set defaults to $_SERVER['REQUEST_URI']
     */
    public function parse_path($uri='') {
        if ($uri == '') {
            $uri = $_SERVER['REQUEST_URI'];
        }
        $this->uri = new SpongeUri($this->base_uri, $uri);
    }

    /**
     * This function either redirects an 'id' URI or attempts to return a 'doc' URI.
     * It may check a store for the URI before attempting to generate fresh data.
     * It may post data back to the store after generating
     * This function calls get_rdf() which will be implemented is a user class.
     */
    public function build_response() {
        if ($this->uri->is_thing()) { //redirect id to doc with a 303
            
            if (SPONGE_SEND_RESPONSE)
                $res = new SpongeResponse("303", '', '', $this->uri->get_doc_uri());
        }else if ($this->uri->is_doc()) { // serve some data
           
            $this->debug_print($this->uri->get_thing_uri(1), "thing_uri");

            // check if we allready have this in the store?
            if (SPONGE_STORE_CHECK) {
                $store = new Store($this->store_uri);
                $ss = $store->get_sparql_service();

                $response = $ss->describe($this->uri->get_thing_uri(), 'slcbd');

                if ($response->is_success()) {
                    $this->debug_print($response, "Response from sparql describe for " . $this->uri->get_thing_uri());

                    $graph = new SimpleGraph();
                    $graph->from_rdfxml($response->body);

                    if ($graph->is_empty()) {
                        // generate new set of data
                        $this->debug_print($this->uri->get_thing_uri(), 'Store graph is empty');
                        $data = $this->get_rdf();
                        $this->debug_print($data, "The new graph");
                        if (SPONGE_SEND_RESPONSE) {
                            $res = new SpongeResponse("200", $this->uri->get_content_type(), $data);
                        }
                        //store the new data 
                        if(SPONGE_STORE_POST) $this->store_rdf();
                    } else {
                        // do something with graph...
                        $this->debug_print($response, "Response from store");
                        if (SPONGE_SEND_RESPONSE)
                            $res = new SpongeResponse("200", $this->uri->get_content_type(), $this->serialiseGraph($graph));
                    }
                } else {
                    // TODO else there was no useful response, so we generate some...
                    // $this->debug_print($response,"Sparql Error");

                    if (SPONGE_SEND_RESPONSE)
                        $res = new SpongeResponse("200", $this->uri->get_content_type(), $this->get_rdf());
                }
            } else {
                $data = $this->get_rdf();
                $this->debug_print($data, "The new graph");
                if (SPONGE_SEND_RESPONSE) {
                    $res = new SpongeResponse("200", $this->uri->get_content_type(), $data);
                }
                //store the new data 
                if(SPONGE_STORE_POST) $this->store_rdf();
            }
        }
    }

    /**
     *
     * @param SimpleGraph $g a SimpleGraph object.
     * @return string A serialised RDF graph
     */
    private function serialiseGraph(SimpleGraph $g) {
        $this->debug_print($g, "graph passed to serialiseGraph");
        switch ($this->uri->get_extension()) {
            case 'html':
                return $g->to_html();
                break;
            case 'json':
                return $g->to_json();
                break;
            case 'rdf' :
                return $g->to_rdfxml();
                break;
            case 'ttl' :
                return $g->to_turtle();
                break;
            case 'nt' :
                return $g->to_ntriples();
                break;
            default:
                return $g->to_html();
                break;
        }
    }

    /**
     * Stores the current graph in $this->graph in a Talis Platform Store
     */
    public function store_rdf() {
        $this->debug_print('', "in store_data()");

        $cred = new Credentials($this->store_user, $this->store_pass);
        $store = new Store($this->store_uri, $cred);
        $mb = $store->get_metabox();

        if (!isset($this->graph)) {
            $this->get_rdf();
        }
        $this->debug_print($this->graph, "Our graph");

        $mb->submit_turtle($this->graph->to_turtle());
    }

    /**
     * Creates a new SimpleGraph graph as $this->graph
     */
    public function new_graph() {
        $this->graph = new SimpleGraph();
    }

    /*
     * public getters
     */

    public function get_path() {
        return $this->uri;
    }

    public function get_data() {
        // You implement this.
    }

    public function get_rdf() {

        /**
         * @todo add extra metadata about data creation time if a file is a doc (etc)?
         */

        return $this->serialiseGraph($this->graph);
    }

    /**
     * function to make a value safe to use in a URI
     *
     * @param string $val
     * @return string Which has been sanitised
     */
    public function uri_safe($val) {
        $val = $this->sanitize($val, true, true);
        return $val;
    }

    /**
     * Function: sanitize (borrowed from Chyrp)
     * Returns a sanitized string, typically for URLs.
     *
     * Parameters:
     * @param string $string The string to sanitize.
     * @param bool $force_lowercase Force the string to lowercase?
     * @param bool $strict If set to *true*, will remove all non-alphanumeric characters.
     */
    private function sanitize($string, $force_lowercase = true, $strict = false) {
        $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
            "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
            "â€”", "â€“", ",", "<", ".", ">", "/", "?");
        $clean = trim(str_replace($strip, "", strip_tags($string)));
        $clean = preg_replace('/\s+/', "_", $clean);
        $clean = ($strict) ? preg_replace("/[^a-zA-Z0-9]/", "", $clean) : $clean;
        return ($force_lowercase) ?
                (function_exists('mb_strtolower')) ?
                        mb_strtolower($clean, 'UTF-8') :
                        strtolower($clean) :
                $clean;
    }

    /**
     *
     *
     * Print debug information to browser.
     *
     * @access public
     *
     * @param mixed $var A variable to display to the screen
     * @param string $title The title of the display (uses a h2 element)
     * @param bool $flag Override the SPONGE_DEBUG constant
     */
    public function debug_print($var, $title='', $flag = 0) {
        if (SPONGE_DEBUG || $flag) {
            echo "<h2>" . $title . "</h2>";
            echo "<pre>";
            print_r($var);
            echo "</pre>";
        }
    }

}
?>