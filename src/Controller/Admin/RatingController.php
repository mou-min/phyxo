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

namespace App\Controller\Admin;

use App\DataMapper\UserMapper;
use App\Repository\ImageRepository;
use App\Repository\RateRepository;
use App\Repository\UserRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Functions\Language;
use Phyxo\Functions\Utils;
use Phyxo\Image\DerivativeImage;
use Phyxo\Image\ImageStandardParams;
use Phyxo\Image\SrcImage;
use Phyxo\TabSheet\TabSheet;
use Phyxo\Template\Template;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

class RatingController extends AdminCommonController
{
    protected function setTabsheet(string $section = 'photos'): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('photos', Language::l10n('Photos'), $this->generateUrl('admin_rating'));
        $tabsheet->add('users', Language::l10n('Users'), $this->generateUrl('admin_rating_users'));
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function photos(Request $request, int $start = 0, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params, ImageStandardParams $image_std_params)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $navbar_params = [];
        $elements_per_page = 10;
        if ($request->get('display') && is_numeric($request->get('display'))) {
            $elements_per_page = $request->get('display');
            $navbar_params['display'] = $elements_per_page;
        }

        $order_by_index = 0;
        if ($request->get('order_by') && is_numeric($request->get('order_by'))) {
            $order_by_index = $request->get('order_by');
            $navbar_params['order_by'] = $order_by_index;
        }

        $user_filter = '';
        if ($request->get('users')) {
            if ($request->get('users') === 'user') {
                $user_filter = 'r.user_id != ' . $conf['guest_id'];
            } elseif ($request->get('users') === 'guest') {
                $user_filter = 'r.user_id = ' . $conf['guest_id'];
            }
            $navbar_params['users'] = $request->get('users');
        }

        $users = [];
        $result = $em->getRepository(UserRepository::class)->findAll();
        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            $users[$row['id']] = $row['username'];
        }

        $nb_images = $em->getRepository(RateRepository::class)->countImagesRatedForUser($user_filter);

        $tpl_params['F_ACTION'] = $this->generateUrl('admin_rating', ['start' => $start]);
        $tpl_params['DISPLAY'] = $elements_per_page;
        $tpl_params['NB_ELEMENTS'] = $nb_images;

        $available_order_by = [
            [Language::l10n('Rate date'), 'recently_rated DESC'],
            [Language::l10n('Rating score'), 'score DESC'],
            [Language::l10n('Average rate'), 'avg_rates DESC'],
            [Language::l10n('Number of rates'), 'nb_rates DESC'],
            [Language::l10n('Sum of rates'), 'sum_rates DESC'],
            [Language::l10n('File name'), 'file DESC'],
            [Language::l10n('Creation date'), 'date_creation DESC'],
            [Language::l10n('Post date'), 'date_available DESC'],
        ];

        for ($i = 0; $i < count($available_order_by); $i++) {
            $tpl_params['order_by_options'][] = $available_order_by[$i][0];
        }
        $tpl_params['order_by_options_selected'] = [$order_by_index];

        $user_options = [
            'all' => Language::l10n('all'),
            'user' => Language::l10n('Users'),
            'guest' => Language::l10n('Guests'),
        ];

        $tpl_params['user_options'] = $user_options;
        $tpl_params['user_options_selected'] = [$request->get('users')];

        $images = [];
        $result = $em->getRepository(RateRepository::class)->getRatePerImage(
            $user_filter,
            $available_order_by[$order_by_index][1],
            $elements_per_page,
            $start
        );
        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            $images[] = $row;
        }

        $tpl_params['images'] = [];
        foreach ($images as $image) {
            $thumbnail_src = (new DerivativeImage(new SrcImage($image, $conf['picture_ext']), $image_std_params->getByType(ImageStandardParams::IMG_THUMB), $image_std_params))->getUrl();
            $image_url = $this->generateUrl('admin_photo', ['image_id' => $image['id']]);

            $result = $em->getRepository(RateRepository::class)->findByElementId($image['id']);
            $nb_rates = $em->getConnection()->db_num_rows($result);

            $tpl_image = [
                'id' => $image['id'],
                'U_THUMB' => $thumbnail_src,
                'U_URL' => $image_url,
                'SCORE_RATE' => $image['score'],
                'AVG_RATE' => $image['avg_rates'],
                'SUM_RATE' => $image['sum_rates'],
                'NB_RATES' => (int)$image['nb_rates'],
                'NB_RATES_TOTAL' => (int)$nb_rates,
                'FILE' => $image['file'],
                'rates' => []
            ];

            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                if (isset($users[$row['user_id']])) {
                    $user_rate = $users[$row['user_id']];
                } else {
                    $user_rate = '? ' . $row['user_id'];
                }
                if (strlen($row['anonymous_id']) > 0) {
                    $user_rate .= '(' . $row['anonymous_id'] . ')';
                }

                $row['USER'] = $user_rate;
                $row['md5sum'] = md5($row['user_id'] . $row['element_id'] . $row['anonymous_id']);
                $tpl_image['rates'][] = $row;
            }
            $tpl_params['images'][] = $tpl_image;
        }

        $tpl_params['navbar'] = Utils::createNavigationBar($this->get('router'), 'admin_rating', $navbar_params, $nb_images, $start, $elements_per_page);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_rating');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_rating');
        $tpl_params['PAGE_TITLE'] = Language::l10n('Rating');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('photos'), $tpl_params);

        return $this->render('rating_photos.tpl', $tpl_params);
    }

    public function users(Request $request, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params, UserMapper $userMapper, ImageStandardParams $image_std_params)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $filter_min_rates = 2;
        if ($request->get('f_min_rates') && is_numeric($request->get('f_min_rates'))) {
            $filter_min_rates = $request->get('f_min_rates');
        }

        $consensus_top_number = $conf['top_number'];
        if ($request->get('consensus_top_number') && is_numeric($request->get('consensus_top_number'))) {
            $consensus_top_number = $request->get('consensus_top_number');
        }

        // build users
        $users_by_id = [];
        $result = $em->getRepository(UserRepository::class)->getUserInfosList();
        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            $users_by_id[(int)$row['id']] = [
                'name' => $row['username'],
                'anon' => $userMapper->isClassicUser()
            ];
        }

        $by_user_rating_model = ['rates' => []];
        foreach ($conf['rate_items'] as $rate) {
            $by_user_rating_model['rates'][$rate] = [];
        }

        // by user aggregation
        $image_ids = [];
        $by_user_ratings = [];
        $result = $em->getRepository(RateRepository::class)->findAll();
        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            if (!isset($users_by_id[$row['user_id']])) {
                $users_by_id[$row['user_id']] = ['name' => '???' . $row['user_id'], 'anon' => false];
            }
            $usr = $users_by_id[$row['user_id']];
            if ($usr['anon']) {
                $user_key = $usr['name'] . '(' . $row['anonymous_id'] . ')';
            } else {
                $user_key = $usr['name'];
            }
            $rating = &$by_user_ratings[$user_key];
            if (is_null($rating)) {
                $rating = $by_user_rating_model;
                $rating['uid'] = (int)$row['user_id'];
                $rating['aid'] = $usr['anon'] ? $row['anonymous_id'] : '';
                $rating['last_date'] = $rating['first_date'] = $row['date'];
                $rating['md5sum'] = md5($rating['uid'] . $rating['aid']);
            } else {
                $rating['first_date'] = $row['date'];
            }

            $rating['rates'][$row['rate']][] = [
                'id' => $row['element_id'],
                'date' => $row['date'],
            ];
            $image_ids[$row['element_id']] = 1;
            unset($rating);
        }

        // get image tn urls
        $image_urls = [];
        if (count($image_ids) > 0) {
            $result = $em->getRepository(ImageRepository::class)->findByIds(array_keys($image_ids));
            $d_params = $image_std_params->getByType(ImageStandardParams::IMG_SQUARE);
            while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                $image_urls[$row['id']] = [
                    'tn' => (new DerivativeImage(new SrcImage($row, $conf['picture_ext']), $d_params, $image_std_params))->getUrl(),
                    'page' => \Phyxo\Functions\URL::make_picture_url(['image_id' => $row['id'], 'image_file' => $row['file']]),
                ];
            }
        }

        //all image averages
        $all_img_sum = [];
        $result = $em->getRepository(RateRepository::class)->calculateAverageyElement();
        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            $all_img_sum[(int)$row['element_id']] = ['avg' => (float)$row['avg']];
        }

        $result = $em->getRepository(ImageRepository::class)->findBestRated($consensus_top_number);
        $best_rated = array_flip($em->getConnection()->result2array($result, null, 'id'));

        // by user stats
        foreach ($by_user_ratings as $id => &$rating) {
            $c = 0;
            $s = 0;
            $ss = 0;
            $consensus_dev = 0;
            $consensus_dev_top = 0;
            $consensus_dev_top_count = 0;
            foreach ($rating['rates'] as $rate => $rates) {
                $ct = count($rates);
                $c += $ct;
                $s += $ct * $rate;
                $ss += $ct * $rate * $rate;
                foreach ($rates as $id_date) {
                    $dev = abs($rate - $all_img_sum[$id_date['id']]['avg']);
                    $consensus_dev += $dev;
                    if (isset($best_rated[$id_date['id']])) {
                        $consensus_dev_top += $dev;
                        $consensus_dev_top_count++;
                    }
                }
            }

            $consensus_dev /= $c;
            if ($consensus_dev_top_count) {
                $consensus_dev_top /= $consensus_dev_top_count;
            }

            $var = ($ss - $s * $s / $c) / $c;
            $rating += [
                'id' => $id,
                'count' => $c,
                'avg' => $s / $c,
                'cv' => $s == 0 ? -1 : sqrt($var) / ($s / $c), // http://en.wikipedia.org/wiki/Coefficient_of_variation
                'cd' => $consensus_dev,
                'cdtop' => $consensus_dev_top_count ? $consensus_dev_top : '',
            ];
        }
        unset($rating);

        // filter
        foreach ($by_user_ratings as $id => $rating) {
            if ($rating['count'] <= $filter_min_rates) {
                unset($by_user_ratings[$id]);
            }
        }

        $order_by_index = 4;
        if ($request->get('order_by') && is_numeric($request->get('order_by'))) {
            $order_by_index = $request->get('order_by');
        }

        $available_order_by = [
            [Language::l10n('Average rate'), 'avg_compare'],
            [Language::l10n('Number of rates'), 'count_compare'],
            [Language::l10n('Variation'), 'cv_compare'],
            [Language::l10n('Consensus deviation'), 'consensus_dev_compare'],
            [Language::l10n('Last'), 'last_rate_compare'],
        ];

        for ($i = 0; $i < count($available_order_by); $i++) {
            $tpl_params['order_by_options'][] = $available_order_by[$i][0];
        }

        $tpl_params['order_by_options_selected'] = [$order_by_index];
        $x = uasort($by_user_ratings, [$this, $available_order_by[$order_by_index][1]]);

        $tpl_params['F_ACTION'] = $this->generateUrl('admin_rating_users');
        $tpl_params['F_MIN_RATES'] = $filter_min_rates;
        $tpl_params['CONSENSUS_TOP_NUMBER'] = $consensus_top_number;
        $tpl_params['available_rates'] = $conf['rate_items'];
        $tpl_params['ratings'] = $by_user_ratings;
        $tpl_params['image_urls'] = $image_urls;
        $tpl_params['TN_WIDTH'] = $image_std_params->getByType(ImageStandardParams::IMG_SQUARE)->sizing->ideal_size[0];

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_rating');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_rating');
        $tpl_params['PAGE_TITLE'] = Language::l10n('Rating');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('users'), $tpl_params);

        return $this->render('rating_users.tpl', $tpl_params);
    }

    protected function avg_compare($a, $b)
    {
        $d = $a['avg'] - $b['avg'];
        return ($d == 0) ? 0 : ($d < 0 ? -1 : 1);
    }

    protected function count_compare($a, $b)
    {
        $d = $a['count'] - $b['count'];
        return ($d == 0) ? 0 : ($d < 0 ? -1 : 1);
    }

    protected function cv_compare($a, $b)
    {
        $d = $b['cv'] - $a['cv']; //desc
        return ($d == 0) ? 0 : ($d < 0 ? -1 : 1);
    }

    protected function consensus_dev_compare($a, $b)
    {
        $d = $b['cd'] - $a['cd']; //desc
        return ($d == 0) ? 0 : ($d < 0 ? -1 : 1);
    }

    protected function last_rate_compare($a, $b)
    {
        return -strcmp($a['last_date'], $b['last_date']);
    }
}
