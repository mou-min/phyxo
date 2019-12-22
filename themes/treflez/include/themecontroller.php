<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Treflez;

use Phyxo\Image\SrcImage;
use Phyxo\Functions\Plugin;
use Phyxo\Functions\Utils;
use Phyxo\Functions\Metadata;
use Phyxo\Functions\URL;
use App\Repository\ImageRepository;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Phyxo\Conf;
use Phyxo\Image\ImageStandardParams;
use Symfony\Contracts\Translation\TranslatorInterface;

class ThemeController
{
    private $config, $core_config, $template, $translator;

    public function __construct(Conf $conf, EngineInterface $template, TranslatorInterface $translator)
    {
        $this->core_config = $conf;
        $this->config = new Config($conf);
        $this->template = $template;
        $this->translator = $translator;
    }

    public function init()
    {
        $this->setInitValues();
        $this->assignConfig();

        Plugin::add_event_handler('loc_after_page_header', [$this, 'stripBreadcrumbs']);
        Plugin::add_event_handler('format_exif_data', [$this, 'exifReplacements']);

        Plugin::add_event_handler('loc_end_picture', [$this, 'getAllThumbnailsInCategory']);
    }

    public function assignConfig()
    {
        if (isset($this->core_config['bootstrap_darkroom_navbar_main_style']) && !empty($this->core_config['bootstrap_darkroom_navbar_main_style'])) {
            $this->config->navbar_main_style = $this->core_config['bootstrap_darkroom_navbar_main_style'];
        }

        if (isset($this->core_config['bootstrap_darkroom_navbar_main_bg']) && !empty($this->core_config['bootstrap_darkroom_navbar_main_bg'])) {
            $this->config->navbar_main_bg = $this->core_config['bootstrap_darkroom_navbar_main_bg'];
        }

        if (isset($this->core_config['bootstrap_darkroom_navbar_contextual_style']) && !empty($this->core_config['bootstrap_darkroom_navbar_contextual_style'])) {
            $this->config->navbar_contextual_style = $this->core_config['bootstrap_darkroom_navbar_contextual_style'];
        }

        if (isset($this->core_config['bootstrap_darkroom_navbar_contextual_bg']) && !empty($this->core_config['bootstrap_darkroom_navbar_contextual_bg'])) {
            $this->config->navbar_contextual_bg = $this->core_config['bootstrap_darkroom_navbar_contextual_bg'];
        }

        $this->template->assign('theme_config', $this->config);
    }

    public function setInitValues()
    {
        // // register video files
        // $video_ext = ['mp4', 'm4v'];
        // if (!empty($conf['file_ext'])) {
        //     $conf['file_ext'] = array_merge($conf['file_ext'], $video_ext, array_map('strtoupper', $video_ext));
        // }

        $this->template->assign([
            'col_padding' => '',
            'meta_ref_enabled' => $this->core_config['meta_ref'],
            'AUTHORIZE_REMEMBERING' => $this->core_config['authorize_remembering'],
        ]);
    }

    public function exifReplacements(array $exif = [])
    {
        if (isset($this->core_config['bootstrap_darkroom_ps_exif_replacements'])) {
            foreach ($this->core_config['bootstrap_darkroom_ps_exif_replacements'] as $tag => $replacement) {
                if (isset($exif[$tag])) {
                    $exif[$tag] = str_replace($replacement[0], $replacement[1], $exif[$tag]);
                }
            }
        }

        return $exif;
    }

    public function stripBreadcrumbs()
    {
        global $page;

        $l_sep = $this->template->get_template_vars('LEVEL_SEPARATOR');
        $title = $this->template->get_template_vars('TITLE');
        $section_title = $this->template->get_template_vars('SECTION_TITLE');
        if (empty($title)) {
            $title = $section_title;
        }
        if (!empty($title)) {
            $splt = strpos($title, "[");
            if ($splt) {
                $title_links = substr($title, 0, $splt);
                $title = $title_links;
            }

            $title = str_replace('<a href', '<a class="nav-breadcrumb-item" href', $title);
            $title = str_replace($l_sep, '', $title);
            if ($page['section'] == 'recent_cats' or $page['section'] == 'favorites') {
                $title = preg_replace('/<\/a>([a-zA-Z0-9]+)/', '</a><a class="nav-breadcrumb-item" href="' . \Phyxo\Functions\URL::make_index_url(['section' => $page['section']]) . '">${1}', $title) . '</a>';
            }
            if (empty($section_title)) {
                $this->template->assign('TITLE', $title);
            } else {
                $this->template->assign('SECTION_TITLE', $title);
            }
        }
    }

    public function getAllThumbnailsInCategory()
    {
        global $page, $conn;

        if (!$page['items'] || ($page['section'] == 'categories' && !isset($page['category']))) {
            return;
        }

        $selection = $page['items'];
        $selection = Plugin::trigger_change('loc_index_thumbnails_selection', $selection);

        if (count($selection) > 0) {
            $rank_of = array_flip($selection);

            $result = (new ImageRepository($conn))->findByIds($selection);
            while ($row = $conn->db_fetch_assoc($result)) {
                $row['rank'] = $rank_of[$row['id']];
                $pictures[] = $row;
            }

            usort($pictures, '\Phyxo\Functions\Utils::rank_compare');
            unset($rank_of);
        }

        $tpl_thumbnails_var = [];

        $theme_config = $this->template->get_template_vars('theme_config');

        if ($theme_config->photoswipe_metadata) {
            if (isset($this->core_config['bootstrap_darkroom_ps_exif_mapping'])) {
                $exif_mapping = $this->core_config['bootstrap_darkroom_ps_exif_mapping'];
            } else {
                $exif_mapping = [
                    'date_creation' => 'DateTimeOriginal',
                    'make' => 'Make',
                    'model' => 'Model',
                    'lens' => 'UndefinedTag:0xA434',
                    'shutter_speed' => 'ExposureTime',
                    'iso' => 'ISOSpeedRatings',
                    'apperture' => 'FNumber',
                    'focal_length' => 'FocalLength',
                ];
            }
        }

        foreach ($pictures as $row) {
            $url = URL::duplicate_picture_url(
                [
                    'image_id' => $row['id'],
                    'image_file' => $row['file'],
                ],
                ['start']
            );

            $name = Utils::render_element_name($row);
            $desc = Utils::render_element_description($row, 'main_page_element_description');

            $tpl_var = array_merge($row, [
                'NAME' => $name,
                'TN_ALT' => htmlspecialchars(strip_tags($name)),
                'TN_TITLE' => \Phyxo\Functions\Utils::get_thumbnail_title($row, $row['name'], $row['comment']),
                'URL' => $url,
                'DESCRIPTION' => htmlspecialchars(strip_tags($desc)),
                'src_image' => new SrcImage($row, $this->core_config['picture_ext']),
                'SIZE' => $row['width'] . 'x' . $row['height'],
                'PATH' => $row['path'],
                'DATE_CREATED' => $row['date_creation'],
            ]);

            if ($theme_config->photoswipe_metadata) {
                $tpl_var = array_merge($tpl_var, [
                    'EXIF' => Metadata::get_exif_data($row['path'], $exif_mapping),
                ]);

                //optional replacements
                if (isset($this->core_config['bootstrap_darkroom_ps_exif_replacements'])) {
                    foreach ($this->core_config['bootstrap_darkroom_ps_exif_replacements'] as $tag => $replacement) {
                        if (array_key_exists($tag, $tpl_var['EXIF'])) {
                            $tpl_var['EXIF'][$tag] = str_replace($replacement[0], $replacement[1], $tpl_var['EXIF'][$tag]);
                        }
                    }
                }
            }

            $tpl_thumbnails_var[] = $tpl_var;
        }

        $this->template->assign('thumbnails', $tpl_thumbnails_var);

        $this->template->assign([
            'derivative_params_square' => Plugin::trigger_change('get_index_derivative_params', $this->template->getImageStandardParams()->getByType(ImageStandardParams::IMG_SQUARE)),
            'derivative_params_medium' => Plugin::trigger_change('get_index_derivative_params', $this->template->getImageStandardParams()->getByType(ImageStandardParams::IMG_MEDIUM)),
            'derivative_params_large' => Plugin::trigger_change('get_index_derivative_params', $this->template->getImageStandardParams()->getByType(ImageStandardParams::IMG_LARGE)),
            'derivative_params_xxlarge' => Plugin::trigger_change('get_index_derivative_params', $this->template->getImageStandardParams()->getByType(ImageStandardParams::IMG_XXLARGE)),
        ]);

        unset($tpl_thumbnails_var, $pictures);
    }
}
