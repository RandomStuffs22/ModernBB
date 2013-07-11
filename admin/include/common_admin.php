<?php

/**
 * Copyright (C) 2013 ModernBB
 * Based on code by FluxBB copyright (C) 2008-2012 FluxBB
 * Based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 3 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

// Make sure we have a usable language pack for admin.
if (file_exists(FORUM_ROOT.'lang/'.$pun_user['language'].'/admin_common.php'))
	$admin_language = $pun_user['language'];
else if (file_exists(FORUM_ROOT.'lang/'.$pun_config['o_default_lang'].'/admin_common.php'))
	$admin_language = $pun_config['o_default_lang'];
else
	$admin_language = 'English';

// Attempt to load the admin_common language file
require FORUM_ROOT.'lang/'.$admin_language.'/admin_common.php';

//
// Display the admin navigation menu
//
function generate_admin_menu($page = '')
{
	global $pun_config, $pun_user, $lang_admin_common;

	$is_admin = $pun_user['g_id'] == PUN_ADMIN ? true : false;

?>
<div class="navbar navbar-static-top">
  <div class="navbar-inner">
  <div class="container">
    <a class="brand" href="admin_index.php">ModernBB</a>
    <ul class="nav">
      <li><a href="admin_index.php">Dashboard</a></li>
      <li class="dropdown">
		<a href="#" class="dropdown-toggle" data-toggle="dropdown">
		  Content <b class="caret"></b>
		</a>
		<ul class="dropdown-menu">
		  <?php if ($is_admin) { ?><li><a href="admin_forums.php">Forums</a></li><?php }; ?>
		  <?php if ($is_admin) { ?><li><a href="admin_categories.php">Categories</a></li><?php }; ?>
		  <?php if ($is_admin) { ?><li><a href="admin_censoring.php">Censoring</a></li><?php }; ?>
		  <li><a href="admin_reports.php">Reports</a></li>
		</ul>
	  </li>
      <li class="dropdown">
		<a href="#" class="dropdown-toggle" data-toggle="dropdown">
		  Users <b class="caret"></b>
		</a>
		<ul class="dropdown-menu">
		  <li><a href="admin_users.php">Users</a></li>
		  <?php if ($is_admin) { ?><li><a href="admin_ranks.php">Ranks</a></li><?php }; ?>
		  <?php if ($is_admin) { ?><li><a href="admin_groups.php">Groups</a></li><?php }; ?>
		  <?php if ($is_admin) { ?><li><a href="admin_permissions.php">Permissions</a></li><?php }; ?>
		  <li><a href="admin_bans.php">Bans</a></li>
		</ul>
	  </li>
      <?php if ($is_admin) { ?><li class="dropdown">
		<a href="#" class="dropdown-toggle" data-toggle="dropdown">
		  Settings <b class="caret"></b>
		</a>
		<ul class="dropdown-menu">
		  <li><a href="admin_options.php">Global</a></li>
		  <li><a href="admin_email.php">Email</a></li>
		  <li><a href="admin_maintenance.php">Maintenance</a></li>
		</ul>
	  </li><?php }; ?>
      <?php if ($is_admin) { ?><li class="dropdown">
		<a href="#" class="dropdown-toggle" data-toggle="dropdown">
		  Extensions <b class="caret"></b>
		</a>
		<ul class="dropdown-menu">
<?php

	// See if there are any plugins
	$plugins = forum_list_plugins($is_admin);

	// Did we find any plugins?
	if (!empty($plugins))
	{

		foreach ($plugins as $plugin_name => $plugin)
			echo "\t\t\t\t\t".'<li class="'.(($page == $plugin_name) ? 'active' : '').'"><a href="admin_loader.php?plugin='.$plugin_name.'">'.str_replace('_', ' ', $plugin).'</a></li>'."\n";

	} else {
		echo '<li class="nav-header">No plugins</li>';
	}
}; ?>
        </ul>
      </li>
      </ul>
    </div>
  </div>
</div>

<?php

}


//
// Delete topics from $forum_id that are "older than" $prune_date (if $prune_sticky is 1, sticky topics will also be deleted)
//
function prune($forum_id, $prune_sticky, $prune_date)
{
	global $db;

	$extra_sql = ($prune_date != -1) ? ' AND last_post<'.$prune_date : '';

	if (!$prune_sticky)
		$extra_sql .= ' AND sticky=\'0\'';

	// Fetch topics to prune
	$result = $db->query('SELECT id FROM '.$db->prefix.'topics WHERE forum_id='.$forum_id.$extra_sql, true) or error('Unable to fetch topics', __FILE__, __LINE__, $db->error());

	$topic_ids = '';
	while ($row = $db->fetch_row($result))
		$topic_ids .= (($topic_ids != '') ? ',' : '').$row[0];

	if ($topic_ids != '')
	{
		// Fetch posts to prune
		$result = $db->query('SELECT id FROM '.$db->prefix.'posts WHERE topic_id IN('.$topic_ids.')', true) or error('Unable to fetch posts', __FILE__, __LINE__, $db->error());

		$post_ids = '';
		while ($row = $db->fetch_row($result))
			$post_ids .= (($post_ids != '') ? ',' : '').$row[0];

		if ($post_ids != '')
		{
			// Delete topics
			$db->query('DELETE FROM '.$db->prefix.'topics WHERE id IN('.$topic_ids.')') or error('Unable to prune topics', __FILE__, __LINE__, $db->error());
			// Delete subscriptions
			$db->query('DELETE FROM '.$db->prefix.'topic_subscriptions WHERE topic_id IN('.$topic_ids.')') or error('Unable to prune subscriptions', __FILE__, __LINE__, $db->error());
			// Delete posts
			$db->query('DELETE FROM '.$db->prefix.'posts WHERE id IN('.$post_ids.')') or error('Unable to prune posts', __FILE__, __LINE__, $db->error());

			// We removed a bunch of posts, so now we have to update the search index
			require_once FORUM_ROOT.'include/search_idx.php';
			strip_search_index($post_ids);
		}
	}
}