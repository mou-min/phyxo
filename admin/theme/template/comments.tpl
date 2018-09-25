{extends file="__layout.tpl"}


{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'User comments'|translate}</a></li>
    <li class="breadcrumb-item">{$NB_ELEMENTS} {$TABSHEET_TITLE}</li>
{/block}

{block name="content"}
    {combine_script id="comments" load="footer" path="admin/theme/js/comments.js"}

    {if !empty($navbar) }{include file="navigation_bar.tpl"}{/if}

    {if !empty($comments) }
	<form method="post" action="{$F_ACTION}">
	    <table class="table table-striped">
		<tbody>
		    {foreach $comments as $comment}
			<tr>
			    <td class="checkComment">
				<input type="checkbox" name="comments[]" value="{$comment.ID}">
			    </td>
			    <td>
				<div class="comment">
				    <a href="{$comment.U_PICTURE}"><img src="{$comment.TN_SRC}"></a>
				    <p>
					{if $comment.IS_PENDING}<span class="pendingFlag">{'Waiting'|translate}</span> - {/if}
					{if !empty($comment.IP)}{$comment.IP} - {/if}<strong>{$comment.AUTHOR}</strong> - <em>{$comment.DATE}</em>
				    </p>
				    <blockquote>{$comment.CONTENT}</blockquote>
				</div>
			    </td>
			</tr>
		    {/foreach}
		</tbody>
	    </table>

	    <p class="checkActions">
		{'Select:'|translate}
		<a href="#" id="commentSelectAll">{'All'|translate}</a>,
		<a href="#" id="commentSelectNone">{'None'|translate}</a>,
		<a href="#" id="commentSelectInvert">{'Invert'|translate}</a>
	    </p>

	    <p class="bottomButtons">
		<input class="btn btn-submit" type="submit" name="validate" value="{'Validate'|translate}">
		<input class="btn btn-cancel" type="submit" name="reject" value="{'Reject'|translate}">
	    </p>

	</form>
    {/if}
{/block}
