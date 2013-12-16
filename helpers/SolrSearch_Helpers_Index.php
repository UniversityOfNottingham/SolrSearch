<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 cc=80; */

/**
 * @package     omeka
 * @subpackage  solr-search
 * @copyright   2012 Rector and Board of Visitors, University of Virginia
 * @license     http://www.apache.org/licenses/LICENSE-2.0.html
 */


class SolrSearch_Helpers_Index
{

    /**
     * This takes an Omeka_Record instance and returns a populated 
     * Apache_Solr_Document.
     *
     * @param Omeka_Db     $db   The database to query.
     * @param Omeka_Record $item The record to index.
     *
     * @return Apache_Solr_Document
     * @author Eric Rochester <erochest@virginia.edu>
     **/
    public static function itemToDocument($db, $item)
    {
        $doc = new Apache_Solr_Document();
        $doc->id = "Item_{$item['id']}";
        $doc->setField('model', 'Item');
        $doc->setField('modelid', $item['id']);
        $doc->setField('url', SolrSearch_Helpers_Index::getUri($item));

        $indexSet = SolrSearch_Helpers_Index::getIndexSet($db);

        $elementTexts = $db
            ->getTable('ElementText')
            ->findBySql('record_id = ?', array($item['id']));

        foreach ($elementTexts as $elementText) {
            if (array_key_exists($elementText['element_id'], $indexSet)) {
                $fieldName = $indexSet[$elementText['element_id']];
                $doc->setMultiValue($fieldName, $elementText['text']);

                if ($elementText['element_id'] == 50) {
                    $doc->setMultiValue('title', $elementText['text']);
                }
            }
        }

        $doc->setMultiValue('resulttype', 'Item');

        if (array_key_exists('tag', $indexSet)) {
            foreach ($item->getTags() as $tag) {
                $doc->setMultiValue('tag', $tag->name);
            }
        }

        if (array_key_exists('collection', $indexSet)
            && $item['collection_id'] > 0
        ) {
            $collectionName = $db
                ->getTable('Collection')
                ->find($item['collection_id'])
                ->name;
            $doc->collection = $collectionName;
        }

        // Item Type
        if (array_key_exists('itemtype', $indexSet) && $item['item_type_id'] > 0) {
            $itemType = $db
                ->getTable('ItemType')
                ->find($item['item_type_id'])
                ->name;
            $doc->itemtype = $itemType;
        }

        // Images
        $files = $item->Files;
        foreach ((array)$files as $file) {
            $mimeType = $file->mime_browser;
            if ($file->has_derivative_image == 1) {
                $doc->setMultiValue('image', $file['id']);
            }
        }

        return $doc;
    }

    /**
     * This returns the URI for an Omeka_Record.
     *
     * @param Omeka_Record $record The record to return the URI for.
     *
     * @return string $uri The URI to access the record with.
     * @author Eric Rochester <erochest@virginia.edu>
     **/
    public static function getUri($record)
    {
        $uri    = '';
        $action = 'show';
        $rc     = get_class($record);

        if ($rc === 'SimplePagesPage') {
            // TODO: If page is the home page, point to root URL.
            $uri = url($record->slug);

        } else if ($rc === 'ExhibitPage') {
            $exhibit = $record->getExhibit();
            $exUri   = SolrSearch_Helpers_Index::getSlugUri($exhibit, $action);
            $uri     = "$exUri/$record->slug";

        } else if (property_exists($record, 'slug')) {
            $uri = SolrSearch_Helpers_Index::getSlugUri($record, $action);

        } else {
            $uri = record_url($record, $action);
        }

        // Always index public URLs.
        $uri = preg_replace('|/admin/|', '/', $uri, 1);

        return $uri;
    }

    /**
     * This returns the URL for an Omeka_Record with a 'slug' property.
     *
     * @param Omeka_Record $record The sluggable record to create the URL for.
     * @param string       $action The action to access the record with.
     *
     * @return string $uri The URI for the record.
     * @author Eric Rochester <erochest@virginia.edu>
     **/
    public static function getSlugURI($record, $action)
    {
        // Copied from omeka/applications/helpers/UrlFunctions.php, record_uri.
        // Yuck.
        $recordClass = get_class($record);
        $inflector   = new Zend_Filter_Word_CamelCaseToDash();
        $controller  = strtolower($inflector->filter($recordClass));
        $controller  = Inflector::pluralize($controller);
        $options     = array(
            'controller' => $controller,
            'action'     => $action,
            'id'         => $record->slug
        );
        $uri = url($options, 'id');

        return $uri;
    }

    /**
     * This returns a set of fields to be indexed by Solr according to the
     * solr_search_facet table.
     *
     * The fields can be either the element IDs or the names of categories like
     * 'description'.
     *
     * @param Omeka_Db $db The database to query.
     *
     * @return array $fieldSet The set of fields to index.
     * @author Eric Rochester <erochest@virginia.edu>
     **/
    public static function getIndexSet($db)
    {
        $fieldSet = array();

        $facets = $db
            ->getTable('SolrSearchFacet')
            ->findAll();

        foreach ($facets as $facet) {
            if ($facet->is_displayed || $facet->is_facet) {
                $key = $facet->element_id ? $facet->element_id : $facet->name;
                $fieldSet[$key] = $facet->name;
            }
        }

        return $fieldSet;
    }

    /**
     * This index the content of an XML file into a Solr document.
     *
     * @param string               $filename The name of the file to index.
     * @param Apache_Solr_Document $solrDoc  The document to index into.
     *
     * @return null
     * @author Eric Rochester <erochest@virginia.edu>
     */
    protected static function _indexXml($filename, $solrDoc)
    {
        $xml = new DomDocument();
        $xml->load($filename);
        $xpath = new DOMXPath($xml);

        $nodes = $xpath->query('//text()');
        foreach ($nodes as $node) {
            $value = preg_replace('/\s\s+/', ' ', trim($node->nodeValue));
            if ($value != ' ' && $value != '') {
                $solrDoc->setMultiValue('fulltext', $value);
            }
        }
    }

    /**
     * This pings the Solr server with the given options and returns true if 
     * it's currently up.
     *
     * @param array $options The configuration to test. Missing values will be 
     * pulled from the current set of options.
     *
     * @return bool
     * @author Eric Rochester <erochest@virginia.edu>
     */
    public static function pingSolrServer($options)
    {
        $server = array_key_exists('solr_search_host', $options)
            ? $options['solr_search_host']
            : get_option('solr_search_host');
        $port   = array_key_exists('solr_search_port', $options)
            ? $options['solr_search_port']
            : get_option('solr_search_port');
        $core   = array_key_exists('solr_search_core', $options)
            ? $options['solr_search_core']
            : get_option('solr_search_core');
        $solr   = new Apache_Solr_Service($server, $port, $core);
        return $solr->ping();
    }


    /**
     * This deletes everything in the Solr index.
     *
     * @return void
     * @author Eric Rochester
     **/
    public static function deleteAll($options)
    {
        $server = array_key_exists('solr_search_host', $options)
            ? $options['solr_search_host']
            : get_option('solr_search_host');
        $port   = array_key_exists('solr_search_port', $options)
            ? $options['solr_search_port']
            : get_option('solr_search_port');
        $core   = array_key_exists('solr_search_core', $options)
            ? $options['solr_search_core']
            : get_option('solr_search_core');
        $solr   = new Apache_Solr_Service($server, $port, $core);

        $solr->deleteByQuery('*:*');
        $solr->commit();
        $solr->optimize();
    }

    /**
     * This re-indexes everything in the Omeka DB.
     *
     * @return void
     * @author Eric Rochester
     **/
    public static function indexAll($options)
    {
        $server = array_key_exists('solr_search_host', $options)
            ? $options['solr_search_host']
            : get_option('solr_search_host');
        $port   = array_key_exists('solr_search_port', $options)
            ? $options['solr_search_port']
            : get_option('solr_search_port');
        $core   = array_key_exists('solr_search_core', $options)
            ? $options['solr_search_core']
            : get_option('solr_search_core');
        $solr   = new Apache_Solr_Service($server, $port, $core);

        $db     = get_db();
        $table  = $db->getTable('Item');
        $select = $table->getSelect();

        $table->filterByPublic($select, true);
        $table->applySorting($select, 'id', 'ASC');

        // First get the items.
        $pager = new SolrSearch_DbPager($db, $table, $select);
        while ($items = $pager->next()) {
            foreach ($items as $item) {
                $docs = array();
                $doc = SolrSearch_Helpers_Index::itemToDocument($db, $item);
                $docs[] = $doc;
                $solr->addDocuments($docs);
            }
            $solr->commit();
        }

        // Now the other addon stuff.
        $mgr  = new SolrSearch_Addon_Manager($db);
        $docs = $mgr->reindexAddons();
        $solr->addDocuments($docs);
        $solr->commit();

        $solr->optimize();
    }

}