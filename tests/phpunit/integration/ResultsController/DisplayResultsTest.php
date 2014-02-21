<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 cc=80; */

/**
 * @package     omeka
 * @subpackage  solr-search
 * @copyright   2012 Rector and Board of Visitors, University of Virginia
 * @license     http://www.apache.org/licenses/LICENSE-2.0.html
 */

class ResultsControllerTest_DisplayResults extends SolrSearch_Case_Default
{


    protected $_isAdminTest = false;


    /**
     * The current query should be populated in the search box.
     */
    public function testPopulateSearchBox()
    {

        // Search for "query."
        $this->request->setMethod('GET')->setParam('q', 'query');
        $this->dispatch('solr-search/results');

        // Should populate the search box.
        $this->assertXpath('//input[@name="q"][@value="query"]');

    }


    /**
     * When an empty query is entered, all documents should be listed.
     */
    public function testReturnAllDocumentsForEmptyQuery()
    {
        // TODO
    }


}