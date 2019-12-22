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

namespace App\DataMapper;

use App\Events\CommentEvent;
use Phyxo\Functions\Plugin;
use Phyxo\DBLayer\iDBLayer;
use Phyxo\Conf;
use App\Repository\CommentRepository;
use App\Repository\UserCacheRepository;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommentMapper
{
    private $conn, $conf, $userMapper, $router, $eventDispatcher, $translator;

    public function __construct(iDBLayer $conn, Conf $conf, UserMapper $userMapper, RouterInterface $router, EventDispatcherInterface $eventDispatcher, TranslatorInterface $translator)
    {
        $this->conn = $conn;
        $this->conf = $conf;
        $this->userMapper = $userMapper;
        $this->router = $router;
        $this->eventDispatcher = $eventDispatcher;
        $this->translator = $translator;
    }

    public function getUser()
    {
        return $this->userMapper->getUser();
    }

    /**
     * Does basic check on comment and returns action to perform.
     * This method is called by a trigger_change()
     *
     * @param string $action before check
     * @param array $comment
     * @return string validate, moderate, reject
     */
    public function userCommentCheck($action, $comment)
    {
        if ($action === 'reject') {
            return $action;
        }

        $my_action = $this->conf['comment_spam_reject'] ? 'reject' : 'moderate';

        if ($action == $my_action) {
            return $action;
        }

        // we do here only BASIC spam check (plugins can do more)
        if (!$this->userMapper->isGuest()) {
            return $action;
        }

        $link_count = preg_match_all('/https?:\/\//', $comment['content'], $matches);

        if (strpos($comment['author'], 'http://') !== false) {
            $link_count++;
        }

        if ($link_count > $this->conf['comment_spam_max_links']) {
            return $my_action;
        }

        return $action;
    }

    /**
     * Tries to insert a user comment and returns action to perform.
     *
     * @param array &$comm
     * @param string $key secret key sent back to the browser
     * @param array &$infos output array of error messages
     * @return string validate, moderate, reject
     */
    public function insertUserComment(&$comm, $key, &$infos)
    {
        $comm = array_merge(
            $comm,
            [
                'ip' => $_SERVER['REMOTE_ADDR'],
                'agent' => $_SERVER['HTTP_USER_AGENT']
            ]
        );

        $infos = [];
        if (!$this->conf['comments_validation'] || $this->userMapper->isAdmin()) {
            $comment_action = 'validate'; //one of validate, moderate, reject
        } else {
            $comment_action = 'moderate'; //one of validate, moderate, reject
        }

        // display author field if the user status is guest
        if ($this->userMapper->isGuest()) {
            if (empty($comm['author'])) {
                if ($this->conf['comments_author_mandatory']) {
                    $infos[] = $this->translator->trans('Username is mandatory');
                    $comment_action = 'reject';
                }
                $comm['author'] = 'guest';
            }
            $comm['author_id'] = $this->conf['guest_id'];
            // if a guest try to use the name of an already existing user, he must be rejected
            if ($comm['author'] !== 'guest') {
                if ((new UserRepository($this->conn))->isUserExists($comm['author'])) {
                    $infos[] = $this->translator->trans('This login is already used by another user');
                    $comment_action = 'reject';
                }
            }
        } else {
            $comm['author'] = $this->getUser()->getUsername();
            $comm['author_id'] = $this->getUser()->getId();
        }

        if (empty($comm['content'])) { // empty comment content
            $comment_action = 'reject';
        }

        if (!\Phyxo\Functions\Utils::verify_ephemeral_key($key, $comm['image_id'])) {
            $comment_action = 'reject';
        }

        // website
        if (!empty($comm['website_url'])) {
            if (!$this->conf['comments_enable_website']) { // honeypot: if the field is disabled, it should be empty !
                $comment_action = 'reject';
            } else {
                $comm['website_url'] = strip_tags($comm['website_url']);
                if (!preg_match('/^https?/i', $comm['website_url'])) {
                    $comm['website_url'] = 'http://' . $comm['website_url'];
                }
                if (!\Phyxo\Functions\Utils::url_check_format($comm['website_url'])) {
                    $infos[] = $this->translator->trans('Your website URL is invalid');
                    $comment_action = 'reject';
                }
            }
        }

        // email
        if (empty($comm['email'])) {
            if (!empty($this->getUser()->getMailAddress())) {
                $comm['email'] = $this->getUser()->getMailAddress();
            } elseif ($this->conf['comments_email_mandatory']) {
                $infos[] = $this->translator->trans('Email address is missing. Please specify an email address.');
                $comment_action = 'reject';
            }
        } elseif (!\Phyxo\Functions\Utils::email_check_format($comm['email'])) {
            $infos[] = $this->translator->trans('mail address must be like xxx@yyy.eee (example : jack@altern.org)');
            $comment_action = 'reject';
        }

        // anonymous id = ip address
        $ip_components = explode('.', $comm['ip']);
        if (count($ip_components) > 3) {
            array_pop($ip_components);
        }
        $anonymous_id = implode('.', $ip_components);

        if ($comment_action != 'reject' && $this->conf['anti-flood_time'] > 0 && !$this->userMapper->isAdmin()) { // anti-flood system
            $reference_date = $this->conn->db_get_flood_period_expression($this->conf['anti-flood_time']);
            $counter = (new CommentRepository($this->conn))->countAuthorMessageNewerThan(
                $comm['author_id'],
                $reference_date,
                !$this->userMapper->isGuest() ? $anonymous_id : null
            );
            if ($counter > 0) {
                $infos[] = $this->translator->trans('Anti-flood system : please wait for a moment before trying to post another comment');
                $comment_action = 'reject';
            }
        }

        // perform more spam check
        $comment_action = Plugin::trigger_change('user_comment_check', $comment_action, $comm);

        if ($comment_action != 'reject') {
            $comm['id'] = (new CommentRepository($this->conn))->addComment([
                'author' => $comm['author'],
                'author_id' => $comm['author_id'],
                'anonymous_id' => $comm['ip'],
                'content' => $comm['content'],
                'date' => 'now()',
                'validated' => $comment_action === 'validate',
                'image_id' => $comm['image_id'],
                'website_url' => (!empty($comm['website_url']) ? $comm['website_url'] : ''),
                'email' => (!empty($comm['email']) ? $comm['email'] : '')
            ]);

            $this->invalidateUserCacheNbComments();

            if (($this->conf['email_admin_on_comment'] && 'validate' == $comment_action)
                || ($this->conf['email_admin_on_comment_validation'] && 'moderate' == $comment_action)) {
                $this->eventDispatcher->dispatch(new CommentEvent($comm, $comment_action));
            }
        }

        return $comment_action;
    }

    /**
     * Tries to delete a (or more) user comment.
     *    only admin can delete all comments
     *    other users can delete their own comments
     */
    public function deleteUserComment(array $comment_ids): bool
    {
        if ((new CommentRepository($this->conn))->deleteByIds($comment_ids, !$this->userMapper->isAdmin() ? $this->getUser()->getId() : null)) {
            $this->invalidateUserCacheNbComments();

            $this->eventDispatcher->dispatch(new CommentEvent(['ids' => $comment_ids, 'author' => $this->getUser()->getUsername()], 'delete'));

            return true;
        }

        return false;
    }

    /**
     * Tries to update a user comment
     *    only admin can update all comments
     *    users can edit their own comments if admin allow them
     *
     * @param array $comment
     * @param string $post_key secret key sent back to the browser
     * @return string validate, moderate, reject
     */
    public function updateUserComment($comment, $post_key)
    {
        $comment_action = 'validate';

        if (!\Phyxo\Functions\Utils::verify_ephemeral_key($post_key, $comment['image_id'])) {
            $comment_action = 'reject';
        } elseif (!$this->conf['comments_validation'] || $this->userMapper->isAdmin()) { // should the updated comment must be validated
            $comment_action = 'validate'; //one of validate, moderate, reject
        } else {
            $comment_action = 'moderate'; //one of validate, moderate, reject
        }

        // perform more spam check
        $comment_action =
            Plugin::trigger_change(
            'user_comment_check',
            $comment_action,
            array_merge(
                $comment,
                ['author' => $this->getUser()->getUsername()]
            )
        );

        // website
        if (!empty($comment['website_url'])) {
            $comment['website_url'] = strip_tags($comment['website_url']);
            if (!preg_match('/^https?/i', $comment['website_url'])) {
                $comment['website_url'] = 'http://' . $comment['website_url'];
            }
            if (!\Phyxo\Functions\Utils::url_check_format($comment['website_url'])) {
                //$page['errors'][] = $this->translator->trans('Your website URL is invalid');
                $comment_action = 'reject';
            }
        }

        if ($comment_action !== 'reject') {
            $user_where_clause = '';
            if (!$this->userMapper->isAdmin()) {
                $user_where_clause = ' AND author_id = \'' . $this->conn->db_real_escape_string($this->getUser()->getId()) . '\'';
            }

            $comment['website_url'] = !empty($comment['website_url']) ? $comment['website_url'] : '';
            $comment['validated'] = $comment_action === 'validate';

            $result = (new CommentRepository($this->conn))->updateComment($comment, $user_where_clause);

            // mail admin and ask to validate the comment
            if ($result && $this->conf['email_admin_on_comment_validation'] && $comment_action === 'moderate') {
                $this->eventDispatcher->dispatch(new CommentEvent($comment, $comment_action));
            } elseif ($result) {
                $this->eventDispatcher->dispatch(new CommentEvent(['author' => $this->getUser()->getUsername(), 'content' => $comment['content']], 'edit'));
            }
        }

        return $comment_action;
    }

    /**
     * Tries to validate a user comment.
     *
     * @param int|int[] $comment_id
     */
    public function validateUserComment($comment_id)
    {
        (new CommentRepository($this->conn))->validateUserComment($comment_id);

        $this->invalidateUserCacheNbComments();
        Plugin::trigger_notify('user_comment_validation', $comment_id);
    }

    /**
     * Clears cache of nb comments for all users
     */
    private function invalidateUserCacheNbComments()
    {
        (new UserCacheRepository($this->conn))->invalidateUserCache('nb_available_comments');
    }
}
