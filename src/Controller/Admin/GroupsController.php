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

use App\DataMapper\AlbumMapper;
use App\DataMapper\UserMapper;
use App\Entity\Album;
use App\Entity\Group;
use App\Repository\GroupRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\TabSheet\TabSheet;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class GroupsController extends AdminCommonController
{
    private $translator;

    public function setTabsheet(string $section = 'list', int $group_id = 0): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('list', $this->translator->trans('Groups', [], 'admin'), $this->generateUrl('admin_groups'), 'fa-group');
        $tabsheet->add('perm', $this->translator->trans('Permissions', [], 'admin'), $group_id !== 0 ? $this->generateUrl('admin_group_perm', ['group_id' => $group_id]) : null, 'fa-lock');
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function list(Request $request, EntityManager $em, Conf $conf, ParameterBagInterface $params, TranslatorInterface $translator, GroupRepository $groupRepository)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST')) {
            if ($groupname = $request->request->get('groupname')) {
                if ($groupRepository->isGroupNameExists($groupname)) {
                    $this->addFlash('error', $translator->trans('This name is already used by another group.', [], 'admin'));
                } else {
                    $group = new Group();
                    $group->setName($groupname);
                    $groupRepository->addOrUpdateGroup($group);

                    $this->addFlash('info', $translator->trans('group "{group}" added', ['group' => $groupname], 'admin'));
                }
            } else {
                $this->addFlash('error', $translator->trans('The name of a group must not be empty.', [], 'admin'));
            }
            $this->redirectToRoute('admin_groups');
        }

        $groups = [];
        foreach ($groupRepository->findUsersInGroups() as $group) {
            $groups[$group->getId()] = [
                'MEMBERS' => $group->getUsers(),
                'ID' => $group->getId(),
                'NAME' => $group->getName(),
                'IS_DEFAULT' => $group->isDefault(),
                'U_PERM' => $this->generateUrl('admin_group_perm', ['group_id' => $group->getId()]),
            ];
        }
        $tpl_params['groups'] = $groups;

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_groups');
        $tpl_params['F_ACTION_MERGE'] = $this->generateUrl('admin_groups_action', ['action' => 'merge']);
        $tpl_params['F_ACTION_DUPLICATE'] = $this->generateUrl('admin_groups_action', ['action' => 'duplicate']);
        $tpl_params['F_ACTION_DELETE'] = $this->generateUrl('admin_groups_action', ['action' => 'delete']);
        $tpl_params['F_ACTION_RENAME'] = $this->generateUrl('admin_groups_action', ['action' => 'rename']);
        $tpl_params['F_ACTION_TOGGLE_DEFAULT'] = $this->generateUrl('admin_groups_action', ['action' => 'toggle_default']);
        $tpl_params['PAGE_TITLE'] = $translator->trans('Groups', [], 'admin');
        $tpl_params = array_merge($this->setTabsheet('list'), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_groups');

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);

        return $this->render('groups_list.html.twig', $tpl_params);
    }

    public function perm(Request $request, int $group_id, EntityManager $em, Conf $conf, ParameterBagInterface $params,
                        AlbumMapper $albumMapper, UserMapper $userMapper, TranslatorInterface $translator, GroupRepository $groupRepository)
    {
        $tpl_params = [];
        $this->translator = $translator;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST')) {
            $group = $groupRepository->find($group_id);

            if ($request->request->get('falsify') && $request->request->get('cat_true') && count($request->request->get('cat_true')) > 0) {
                // if you forbid access to a category, all sub-categories become automatically forbidden
                foreach ($albumMapper->getRepository()->getSubAlbums($request->request->get('cat_true')) as $album) {
                    $album->removeGroupAccess($group);
                    $albumMapper->getRepository()->addOrUpdateAlbum($album);
                }
            } elseif ($request->request->get('trueify') && $request->request->get('cat_false') && count($request->request->get('cat_false')) > 0) {
                $uppercats = $albumMapper->getUppercatIds($request->request->get('cat_false'));

                foreach ($albumMapper->getRepository()->findByIdsAndStatus($uppercats, Album::STATUS_PRIVATE) as $album) {
                    $album->addGroupAccess($group);
                    $albumMapper->getRepository()->addOrUpdateAlbum($album);
                }
                $userMapper->invalidateUserCache();
            }
        }

        $groupname = '';
        $group = $groupRepository->find($group_id);
        if (!is_null($group)) {
            $groupname = $group->getName();
        }

        $tpl_params['TITLE'] = $translator->trans('Manage permissions for group "{group}"', ['group' => $groupname], 'admin');
        $tpl_params['L_CAT_OPTIONS_TRUE'] = $translator->trans('Authorized', [], 'admin');
        $tpl_params['L_CAT_OPTIONS_FALSE'] = $translator->trans('Forbidden', [], 'admin');
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_group_perm', ['group_id' => $group_id]);

        // only private categories are listed
        $albums = [];
        $authorized_ids = [];
        foreach ($albumMapper->getRepository()->findPrivateWithGroupAccess($group_id) as $album) {
            $albums[] = $album;
            $authorized_ids[] = $album->getId();
        }
        $tpl_params = array_merge($tpl_params, $albumMapper->displaySelectAlbumsWrapper($albums, [], 'category_option_true'));

        $albums = [];
        foreach ($albumMapper->getRepository()->findUnauthorized($authorized_ids) as $album) {
            $albums[] = $album;
        }
        $tpl_params = array_merge($tpl_params, $albumMapper->displaySelectAlbumsWrapper($albums, [], 'category_option_false'));

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_groups');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Groups', [], 'admin');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('perm', $group_id), $tpl_params);

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_groups');

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('groups_perm.html.twig', $tpl_params);
    }

    public function action(Request $request, string $action, EntityManager $em, GroupRepository $groupRepository, TranslatorInterface $translator)
    {
        $group_selection = $request->request->get('group_selection');
        if (count($group_selection) === 0) {
            $this->addFlash('error', $translator->trans('Select at least one group', [], 'admin'));

            return $this->redirectToRoute('admin_groups');
        }

        if ($action === 'rename') {
            $groups = [];
            $group_names = [];
            foreach ($groupRepository->findAll() as $group) {
                $groups[$group->getId()] = $group;
                $group_names[] = $group->getName();
            }

            foreach ($group_selection as $group_id) {
                if (in_array($request->request->get('rename_' . $group_id), $group_names)) {
                    $this->addFlash('error', $request->request->get('rename_' . $group_id) . ' | ' . $translator->trans('This name is already used by another group.', [], 'admin'));
                } elseif ($rename_group = $request->request->get('rename_' . $group_id)) {
                    $group = $groups[$group_id];
                    $group->setName($rename_group);
                    $groupRepository->addOrUpdateGroup($group);
                }
            }

            return $this->redirectToRoute('admin_groups');
        } elseif ($action === 'delete' && $request->request->get('confirm_deletion')) {
            $groupRepository->deleteByGroupIds($group_selection);

            $this->addFlash('info', $translator->trans('group(s) deleted', [], 'admin'));

            return $this->redirectToRoute('admin_groups');
        } elseif ($action === 'merge' && count($group_selection) > 1) {
            if ($groupRepository->isGroupNameExists($request->request->get('merge'))) {
                $this->addFlash('error', $translator->trans('This name is already used by another group.', [], 'admin'));

                $this->redirectToRoute('admin_groups');
            }

            $group = new Group();
            $group->setName($request->request->get('merge'));

            foreach ($groupRepository->findById($group_selection) as $group_to_merge) {
                if ($group_to_merge->getUsers()->count() > 0) {
                    foreach ($group_to_merge->getUsers() as $user) {
                        $group->addUser($user);
                    }
                }

                if ($group_to_merge->getGroupAccess() > 0) {
                    foreach ($group_to_merge->getGroupAccess() as $album) {
                        $group->addGroupAccess($album);
                    }
                }
            }

            $group_id = $groupRepository->addOrUpdateGroup($group);
            $groupRepository->deleteByGroupIds($group_selection);

            $this->addFlash('info', $translator->trans('group "{group}" added', ['group' => $request->request->get('merge')], 'admin'));

            return $this->redirectToRoute('admin_groups');
        } elseif ($action === 'duplicate') {
            // @TODO: avoid query in loop
            foreach ($group_selection as $group) {
                if (!$request->request->get('duplicate_' . $group)) {
                    break;
                }

                if ($groupRepository->isGroupNameExists($request->request->get('duplicate_' . $group))) {
                    $this->addFlash('error', $translator->trans('This name is already used by another group.', [], 'admin'));
                    break;
                }

                $group_to_duplicate = $groupRepository->find($group);
                $new_group = new Group();
                $new_group->setName($request->request->get('duplicate_' . $group));
                $new_group->setIsDefault($group_to_duplicate->isDefault());
                foreach ($group_to_duplicate->getUsers() as $user) {
                    $new_group->addUser($user);
                }
                foreach ($group_to_duplicate->getGroupAccess() as $album) {
                    $new_group->addGroupAccess($album);
                }
                $group_id = $groupRepository->addOrUpdateGroup($new_group);

                $this->addFlash('info', $translator->trans('group "{group}" added', ['group' => $request->request->get('duplicate_' . $group)], 'admin'));

                return $this->redirectToRoute('admin_groups');
            }
        } elseif ($action === 'toggle_default') {
            $groupRepository->toggleIsDefault($group_selection);

            $this->addFlash('info', $translator->trans('groups "{groups}" updated', ['groups' => implode(', ', $group_selection)], 'admin'));

            return $this->redirectToRoute('admin_groups');
        }

        return $this->redirectToRoute('admin_groups');
    }
}
