<?php

/**
 * @package     omeka
 * @subpackage  solr-search
 * @copyright   2012 Rector and Board of Visitors, University of Virginia
 * @license     http://www.apache.org/licenses/LICENSE-2.0.html
 */

?>

<?php queue_css_file('results'); ?>
<?php echo head(array('title' => __('Search')));?>


<h1><?php echo __('Search the Collection'); ?></h1>


<!-- Search form. -->
<div class="solr">
  <form id="solr-search-form">
    <input type="submit" value="Search" />
    <span class="float-wrap">
      <input type="text" title="<?php echo __('Search keywords') ?>" name="q" value="<?php
        echo array_key_exists('q', $_GET) ? $_GET['q'] : '';
      ?>" />
    </span>
  </form>
</div>


<!-- Applied facets. -->
<div id="solr-applied-facets">

  <ul>

    <!-- Get the applied facets. -->
    <?php foreach (SolrSearch_Helpers_Facet::parseFacets() as $f): ?>
      <li>

        <!-- Facet label. -->
        <?php $label = SolrSearch_Helpers_Facet::keyToLabel($f[0]); ?>
        <span class="applied-facet-label"><?php echo $label; ?></span> >
        <span class="applied-facet-value"><?php echo $f[1]; ?></span>

        <!-- Remove link. -->
        <?php $url = SolrSearch_Helpers_Facet::removeFacet($f[0], $f[1]); ?>
        (<a href="<?php echo $url; ?>">remove</a>)

      </li>
    <?php endforeach; ?>

  </ul>

</div>


<!-- Facets. -->
<div id="solr-facets">

  <h2><?php echo __('Limit your search'); ?></h2>

  <?php foreach ($results->facet_counts->facet_fields as $name => $facets): ?>

    <!-- Does the facet have any hits? -->
    <?php if (count(get_object_vars($facets))): ?>

      <!-- Facet label. -->
      <?php $label = SolrSearch_Helpers_Facet::keyToLabel($name); ?>
      <strong><?php echo $label; ?></strong>

      <ul>
        <!-- Facets. -->
        <?php foreach ($facets as $value => $count): ?>
          <li class="<?php echo $value; ?>">

            <!-- Facet URL. -->
            <?php $url = SolrSearch_Helpers_Facet::addFacet($name, $value); ?>

            <!-- Facet link. -->
            <a href="<?php echo $url; ?>" class="facet-value">
              <?php echo $value; ?>
            </a>

            <!-- Facet count. -->
            (<span class="facet-count"><?php echo $count; ?></span>)

          </li>
        <?php endforeach; ?>
      </ul>

    <?php endif; ?>

  <?php endforeach; ?>
</div>


<!-- Results. -->
<div id="solr-results">

  <!-- Number found. -->
  <h2 id="num-found">
    <?php echo $results->response->numFound; ?> results
  </h2>

  <ul class="results-type">
    <li><a href="<?php echo SolrSearch_Helpers_Facet::makeUrl(SolrSearch_Helpers_Facet::parseFacets()); ?>">Results</a></li>
    <li class="active"><a href="<?php echo SolrSearch_Helpers_Facet::makeUrl(SolrSearch_Helpers_Facet::parseFacets(), url('solr-map')); ?>">Map</a></li>
  </ul>

  <div id="solr-map"></div>

  <?php
  $features = array();
  $geoJson = array(
      'type' => 'FeatureCollection',
      'features' => &$features
  );

  foreach ($results->response->docs as $document) {
    if (isset($document->{'56_g'})) {
      $features[] = array(
          'type' => 'Feature',
          'geometry' => array(
              'type' => 'Point',
              'coordinates' => array_map(
                  function ($value) {
                    return (float) $value;
                  },
                  array_reverse(explode(',', $document->{'56_g'})) // geojson format says longlat. leaflet expects latlong
              )
          ),
          'properties' => array(
              'html' => '<strong>' . $document->title . '</strong>'
          )
      );
    }
  }
  ?>

  <script type="text/javascript">
    var geoJson = <?php echo Zend_Json::encode($geoJson); ?>;
  </script>

</div>

  <style>
    #solr-map { width:100%; height: 500px }
  </style>

  <script>

    var credentials = ({
      id: 'moas-uon.o0j0ka7f',
      accessToken: 'pk.eyJ1IjoibW9hcy11b24iLCJhIjoiY2lmbm9idm1qMDFhaXRjbHhodjQ2NmdsMCJ9.0N-JBlYGHrtjFpyeT0jHDQ'
    });

    var icon = L.icon({
      iconUrl: (Modernizr.svg) ? '<?= img('pin.svg'); ?>' : '<?= img('pin.png'); ?>',
      shadowUrl: '<?= img('pin-shadow.png'); ?>',
      iconSize: [26, 45],
      shadowSize: [36, 32],
      iconAnchor: [12, 44],
      shadowAnchor: [0, 30],
      popupAnchor: [0, -44]
    });

    var map = L.map('solr-map', {
      center: [52.9379898,-1.1749953],
      zoom: 10
    });

    L.tileLayer('https://api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token={accessToken}', {
      attribution: '&copy; <a href="http://openstreetmap.org">OpenStreetMap</a>.',
      maxZoom: 18,
      id: credentials.id,
      accessToken: credentials.accessToken
    }).addTo(map);

    var gj = L.geoJson(geoJson, {
      pointToLayer: function (feature, latlng) {
        return L.marker(latlng, {icon: icon});
      },
      onEachFeature: function (feature, layer) {
        layer.bindPopup(feature.properties.html);
        if (feature.properties.open) {
          layer.openPopup();
        }
      }
    }).addTo(map);

    map.fitBounds(gj.getBounds(), {paddingTopLeft: [10,10], paddingBottomRight: [25,25]});
  </script>

<?php echo foot();
