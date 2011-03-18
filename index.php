<?php

/*
 * Sponge 2 RDF sucks data from wherever you have it and maps it to a predefined model.
 * You can use URIs for both 'things' and 'documents' about a thing.
 *
 * You may want to use the supplied .htaccess file to redirect all requests
 * to the index.php file:
 *
 * @author tim@timhodson.com
 *
 */

// path to moriarty and arc libraries on your server
define('MORIARTY_DIR', '/var/www/lib/moriarty/');
define('MORIARTY_ARC_DIR', '/var/www/lib/ARC/');

require_once 'sponge2rdf.class.php';
require_once 'sponge_builder.interface.php';

/*
 * set to true to see HTML containing the response. this is not clever, but useful :)
 */
if (!defined('SPONGE_DEBUG')) {
    define('SPONGE_DEBUG', false);
}
/*
 * Set to false if you want to run the code without sending an http response.
 * Useful if yyou are just using this to generate data and post to a store.
 */
if (!defined('SPONGE_SEND_RESPONSE')) {
    define('SPONGE_SEND_RESPONSE', true);
}
/*
 * If true, will check your store to see if the data already exists there.
 * If it does a Symetric Labelled Bound Description will be returned
 */
if (!defined('SPONGE_STORE_CHECK')) {
    define('SPONGE_STORE_CHECK', false);
}
/*
 * If set to true will post the generated data back to the store.
 * Useful if you want to harvet data over time.
 */
if (!defined('SPONGE_STORE_POST')) {
    define('SPONGE_STORE_POST', false);
}


/**
 * This class is used to implement get_data() and get_rdf()
 * Can also set the Store details here too.
 */
class MySpecialSponge extends Sponge2Rdf implements SpongeBuilder {

    /**
     *
     * @var string $base_uri of all URIs generated on the fly
     */
    public  $base_uri = "http://localhost";
    /**
     * Talis Platform Store details
     */
    public  $store_uri = "http://api.talis.com/stores/my-shiny-store";
    public  $store_user = '';
    public  $store_pass = '';

    
    public function get_data() {
        /**
         * Implement code here to retrieve some data from where ever it is,
         * using the key from the URI. The URI has already been parsed by 
         * the inherited __construct of class Sponge2Rdf.
         */

        $mykey = $this->uri->get_key();

        // in this example we have hardcoded data
        $data = array(
            'name' => 'Tim',
            'surname' => 'Hodson',
            'nickname' => 'Tim',
            'email' => 'tim@timhodson.com',
            'homepage' => 'http://timhodson.com',
            'special_thing' => "Grandad's gold watch"
        );
        return $data;
    }

    public function get_rdf() {
        /*
         * Call the data function we just defined to get our data.
         */
        $data = $this->get_data();

        // you can include debugging stuff (just output to screen, nothing fancy)
        $this->debug_print($this->uri->get_key(), "key");
       
        // Create a new graph to hold our RDF.
        // The variable $this->graph will hold our SimpleGraph object (see Moriarty docs)
        $this->new_graph();

        // we define some useful prefix constants where they haven't already been defined in Moriarty's constants.inc.php
        // if you are using your own name spaces, you can set these up now too.
        define('FOAF_PREFIX', "http://xmlns.com/foaf/0.1/");
        
        $this->ontology_prefix = 'http://example.com/special-things#';
        $this->graph->set_namespace_mapping("myontology", $this->ontology_prefix);

        /*
         * This example is using a URI like:
         *      /id/person/{identifier}
         *  Where the first container is 'person'
         * Up to four containers can be configured and the rest of your code should use
         *  the SpongeUri object's is_first_container(), is_second_container() etc functions
         *  to test what combination of containers are present in order to determine what to send back.
         */
        if ($this->uri->is_first_container('person')) {
            $this->debug_print('', "we have a person container");
            

            // add a triple for a resource
            $this->graph->add_resource_triple($this->uri->get_thing_uri(1), RDF_TYPE, FOAF_PREFIX."Person");

            // add a triple for a literal
            $this->graph->add_literal_triple($this->uri->get_thing_uri(1), FOAF_PREFIX."family_name", $data['surname']);

            // and so on..
            $this->graph->add_literal_triple($this->uri->get_thing_uri(1), FOAF_PREFIX."givenname", $data['name']);
            $this->graph->add_literal_triple($this->uri->get_thing_uri(1), RDFS_LABEL, $data['name']." ".$data['surname']);
            $this->graph->add_literal_triple($this->uri->get_thing_uri(1), FOAF_NAME, $data['name']." ".$data['surname']);
            $this->graph->add_literal_triple($this->uri->get_thing_uri(1), FOAF_NICK, $data['nickname']);
            $this->graph->add_resource_triple($this->uri->get_thing_uri(1), FOAF_PREFIX."homepage", $data['homepage']);
            $this->graph->add_literal_triple($this->uri->get_thing_uri(1), FOAF_PREFIX."mbox", $data['email']);
            $this->graph->add_literal_triple($this->uri->get_thing_uri(1), FOAF_PREFIX."mbox_sha1sum", sha1($data['email']));
            $this->graph->add_literal_triple($this->uri->get_thing_uri(1), "myontology:specialThing", $data['special_thing']);
        }

        /**
         * Calling the parent class' version of this function will serialise
         * the RDF and optionally add any extra meta rdf, (@todo though this
         * hasn't been implemented.)
         *
         */
        return parent::get_rdf();
    }
}
/**
 * This is where the magic happens!
 *
 * Finally create a new instance of our class.
 *
 * When this page gets hit with a request from the client, everything
 * will be set in motion to work out what they want.
 */
$mySponge = new MySpecialSponge();


?>
