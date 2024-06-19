<?php
/**
 * @package       RSFiles!
 * @copyright (C) 2010-2014 www.rsjoomla.com
 * @license       GPL, http://www.gnu.org/copyleft/gpl.html
 */
defined('_JEXEC') or die('Restricted access');
?>
<form action="<?php echo JRoute::_('index.php?option=com_rsfiles&layout=search'.$this->getOptions()->get('itemid'),false); ?>" method="post" name="adminForm" id="adminForm" class="form-horizontal">
	<div class="card rsfiles-horizontal">
		<div class="card-body">
			<h3>Search</h3>
			<div class="control-group">
				<div class="control-label">
					<label for="filter_search"><?php echo JText::_('COM_RSFILES_SEARCH_KEYWORD'); ?></label>
				</div>
				<div class="controls">
					<input type="text" id="filter_search" name="filter_search" class="form-control" size="30" value="">
				</div>
			</div>
			<div class="control-group">
				<div class="control-label">
					<label><?php echo JText::_('COM_RSFILES_SEARCH_ORDERING'); ?></label>
				</div>
				<div class="controls">
					<select name="rsfl_ordering" id="rsfl_ordering" class="custom-select">
						<option value="name">Name</option>
						<option value="date">Date</option>
						<option value="hits">Hits</option>
					</select>

					<select name="rsfl_ordering_direction" id="rsfl_ordering_direction" class="custom-select">
						<option value="asc">Ascending</option>
						<option value="desc">Descending</option>
					</select>
				</div>
			</div>
			<div class="control-group">
				<div class="control-label"></div>
				<div class="controls">
					<button type="submit" class="btn btn-primary"><?php echo JText::_('COM_RSFILES_SEARCH'); ?></button>
				</div>
			</div>
		</div>
	</div>
	<?php echo JHTML::_( 'form.token' ); ?>
	<input type="hidden" name="task" value="">
</form>
<br />