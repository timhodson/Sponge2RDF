<?php
/**
 * Interface SpongeBuilder
 *
 * When building a class using this interface, you will want to make sure your
 * new class extends Sponge2Rdf.
 *
 * @author tim@timhodson.com
 */

interface SpongeBuilder  {

    /*
     * Get your data from wherever it is .
     *
     * Implement this function to call data from whatever database,
     * file or etherial oldskool storage you are using.
     */
    function get_data() ;

    /*
     * Get some rdf.
     *
     * Implement this function to create the graph you will send back in the response.
     * In this function you will call get_data() then buyild a new graph with the data.
     */
    function get_rdf();
}

?>
