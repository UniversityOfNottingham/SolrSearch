<?php

/**
 * @package     omeka
 * @subpackage  solr-search
 * @copyright   2012 Rector and Board of Visitors, University of Virginia
 * @license     http://www.apache.org/licenses/LICENSE-2.0.html
 */

?>

<?php queue_js_file('accordion'); ?>
<?php queue_css_file('fields'); ?>

<?php echo head(array(
  'title' => __('Solr Search | Facet Configuration'),
)); ?>

<div id="solr-fields">

  <?php echo $this->partial('admin/partials/navigation.php', array(
    'tab' => 'facets'
  )); ?>

  <div id="primary">

    <h2><?php echo __('Menu Order') ?></h2>
    <?php echo flash(); ?>

    <form id="facets-sort-form" method="post">

      <?php if ($facets) : ?>
        <ul id="sortable">
        <?php foreach ($facets as $facet) : ?>
          <li class="ui-state-default" id="<?php echo $facet->id; ?>">
            <input
                name="facets[<?php echo $facet->slug; ?>][id]"
                value="<?php echo $facet->id; ?>"
                type="hidden"
                />
            <strong><?php echo htmlspecialchars($facet->label); ?></strong>
            <span class="move-icon icon icon-move"></span>
          </li>
        <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <input
          name="facetorder"
          value=""
          type="hidden"
          />

      <?php echo $this->formSubmit('submit', __('Update Facet Order')); ?>

    </form>

  </div>
</div>

<?php echo foot(); ?>
