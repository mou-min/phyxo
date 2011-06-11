<?php
// +-----------------------------------------------------------------------+
// | Piwigo - a PHP based photo gallery                                    |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2011 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if( !defined("PHPWG_ROOT_PATH") )
{
  die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
check_status(ACCESS_ADMINISTRATOR);

if (!empty($_POST))
{
  check_pwg_token();
}

// +-----------------------------------------------------------------------+
// |                                edit tags                              |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit']))
{
  $query = '
SELECT name
  FROM '.TAGS_TABLE.'
;';
  $existing_names = array_from_query($query, 'name');
  

  $current_name_of = array();
  $query = '
SELECT id, name
  FROM '.TAGS_TABLE.'
  WHERE id IN ('.$_POST['edit_list'].')
;';
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($result))
  {
    $current_name_of[ $row['id'] ] = $row['name'];
  }
  
  $updates = array();
  // we must not rename tag with an already existing name
  foreach (explode(',', $_POST['edit_list']) as $tag_id)
  {
    $tag_name = stripslashes($_POST['tag_name-'.$tag_id]);

    if ($tag_name != $current_name_of[$tag_id])
    {
      if (in_array($tag_name, $existing_names))
      {
        array_push(
          $page['errors'],
          sprintf(
            l10n('Tag "%s" already exists'),
            $tag_name
            )
          );
      }
      else if (!empty($tag_name))
      {
        array_push(
          $updates,
          array(
            'id' => $tag_id,
            'name' => addslashes($tag_name),
            'url_name' => str2url(trigger_event('render_tag_url', $tag_name)),
            )
          );
      }
    }
  }
  mass_updates(
    TAGS_TABLE,
    array(
      'primary' => array('id'),
      'update' => array('name', 'url_name'),
      ),
    $updates
    );
}

// +-----------------------------------------------------------------------+
// |                               delete tags                             |
// +-----------------------------------------------------------------------+

if (isset($_POST['delete']) and isset($_POST['tags']))
{
  $query = '
SELECT name
  FROM '.TAGS_TABLE.'
  WHERE id IN ('.implode(',', $_POST['tags']).')
;';
  $tag_names = array_from_query($query, 'name');
  
  $query = '
DELETE
  FROM '.IMAGE_TAG_TABLE.'
  WHERE tag_id IN ('.implode(',', $_POST['tags']).')
;';
  pwg_query($query);
  
  $query = '
DELETE
  FROM '.TAGS_TABLE.'
  WHERE id IN ('.implode(',', $_POST['tags']).')
;';
  pwg_query($query);
  
  array_push(
    $page['infos'],
    l10n_dec(
      'The following tag was deleted', 
      'The %d following tags were deleted',
      count($tag_names)).' : '.
      implode(', ', $tag_names)
    );
}

// +-----------------------------------------------------------------------+
// |                           delete orphan tags                          |
// +-----------------------------------------------------------------------+

if (isset($_GET['action']) and 'delete_orphans' == $_GET['action'])
{
  check_pwg_token();
  
  delete_orphan_tags();
  $_SESSION['page_infos'] = array(l10n('Orphan tags deleted'));
  redirect(get_root_url().'admin.php?page=tags');
}

// +-----------------------------------------------------------------------+
// |                               add a tag                               |
// +-----------------------------------------------------------------------+

if (isset($_POST['add']) and !empty($_POST['add_tag']))
{
  $tag_name = $_POST['add_tag'];

  // does the tag already exists?
  $query = '
SELECT id
  FROM '.TAGS_TABLE.'
  WHERE name = \''.$tag_name.'\'
;';
  $existing_tags = array_from_query($query, 'id');

  if (count($existing_tags) == 0)
  {
    mass_inserts(
      TAGS_TABLE,
      array('name', 'url_name'),
      array(
        array(
          'name' => $tag_name,
          'url_name' => str2url(trigger_event('render_tag_url', $tag_name)),
          )
        )
      );
    
    array_push(
      $page['infos'],
      sprintf(
        l10n('Tag "%s" was added'),
        stripslashes($tag_name)
        )
      );
  }
  else
  {
    array_push(
      $page['errors'],
      sprintf(
        l10n('Tag "%s" already exists'),
        stripslashes($tag_name)
        )
      );
  }
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(array('tags' => 'tags.tpl'));

$template->assign(
  array(
    'F_ACTION' => PHPWG_ROOT_PATH.'admin.php?page=tags',
    'PWG_TOKEN' => get_pwg_token(),
    )
  );

// +-----------------------------------------------------------------------+
// |                              orphan tags                              |
// +-----------------------------------------------------------------------+

$orphan_tags = get_orphan_tags();

$orphan_tag_names = array();
foreach ($orphan_tags as $tag)
{
  array_push($orphan_tag_names, $tag['name']);
}

if (count($orphan_tag_names) > 0)
{
  array_push(
    $page['warnings'],
    sprintf(
      l10n('You have %d orphan tags: %s.').' <a href="%s">'.l10n('Delete orphan tags').'</a>',
      count($orphan_tag_names),
      implode(', ', $orphan_tag_names),
      get_root_url().'admin.php?page=tags&amp;action=delete_orphans&amp;pwg_token='.get_pwg_token()
      )
    );
}

// +-----------------------------------------------------------------------+
// |                             form creation                             |
// +-----------------------------------------------------------------------+

$template->assign(
  array(
    'TAG_SELECTION' => get_html_tag_selection(
      get_all_tags(),
      'tags'
      ),
    )
  );

if (isset($_POST['edit']) and isset($_POST['tags']))
{
  $template->assign(
    array(
      'EDIT_TAGS_LIST' => implode(',', $_POST['tags']),
      )
    );

  $query = '
SELECT id, name
  FROM '.TAGS_TABLE.'
  WHERE id IN ('.implode(',', $_POST['tags']).')
;';
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($result))
  {
    $name_of[ $row['id'] ] = $row['name'];
  }

  foreach ($_POST['tags'] as $tag_id)
  {
    $template->append(
      'tags',
      array(
        'ID' => $tag_id,
        'NAME' => $name_of[$tag_id],
        )
      );
  }
}

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'tags');

?>
