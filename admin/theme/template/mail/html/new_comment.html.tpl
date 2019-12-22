{extends file="mail/html/__layout.html.tpl"}

{block name="content"}
    <p>{'Author: {author}'|translate:['author' => $comment.author]}</p>
    {if $comment_action === 'delete'}
	<p>{'This author removed the comment with ids {ids}'|translate:['ids' => $comment.IDS]}</p>
    {elseif $comment_action === 'edit'}
	<p>{'This author modified following comment'|translate}</p>
	<blockquote>{$comment.content}</blockquote>
    {else}
	<p>{'Email: {email}'|translate:['email' => $comment.email]}</p>
	<h3>{'Comment'|translate}</h3>
	<blockquote>{$comment.content}</blockquote>
	{if !empty($comment_url)}
	    <p><a href="{$comment_url}">{'Manage this user comment'|translate}</a></p>
	{/if}
	{if $comment_action === 'moderate'}
	    <p>{'This comment requires validation'|translate}</p>
	{/if}
    {/if}
{/block}
