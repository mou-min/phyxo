{extends file="__layout.tpl"}

{block name="breadcrumb-items"}
    <li class="breadcrumb-item"><a href="{$U_PAGE}">{'User list'|translate}</a></li>
{/block}

{block name="content"}
    {combine_script id="common" load="footer" path="admin/theme/js/common.js"}

    {combine_script id='jquery' path='admin/theme/js/jquery/jquery.js'}
    {combine_script id='jquery.migrate' path='admin/theme/js/jquery/jquery-migrate-1.2.1.js'}
    {combine_script id="jquery.dataTables" load="footer" path="admin/theme/js/plugins/jquery.dataTables.js"}
    {combine_css path="admin/theme/js/plugins/datatables/css/jquery.dataTables.css"}

    {combine_script id="jquery.selectize" load="footer" path="admin/theme/js/plugins/selectize.js"}
    {combine_css id="jquery.selectize" path="admin/theme/js/plugins/selectize.clear.css"}

    {combine_script id="underscore" load="footer" path="admin/theme/js/plugins/underscore.js"}

    {combine_script id="jquery.ui" path="admin/theme/js/ui/jquery.ui.core.js"}
    {combine_script id="jquery.ui.slider" load="footer" path="admin/theme/js/ui/jquery.ui.slider.js"}
    {combine_css path="admin/theme/js/ui/theme/jquery.ui.slider.css"}

    {assign var="selection" value=$selection|default:null}
    {html_head}
    <script>
     var selectedMessage_pattern = "{'%d of %d users selected'|translate|escape:javascript}";
     var selectedMessage_none = "{'No user selected of %d users'|translate|escape:javascript}";
     var selectedMessage_all = "{'All %d users are selected'|translate|escape:javascript}";
     var applyOnDetails_pattern = "{'on the %d selected users'|translate|escape:javascript}";
     var newUser_pattern = "&#x2714; {'User %s added'|translate|escape:javascript}";
     var registeredOn_pattern = "{'Registered on %s, %s.'|translate|escape:javascript}";
     var lastVisit_pattern = "{'Last visit on %s, %s.'|translate|escape:javascript}";
     var missingConfirm = "{'You need to confirm deletion'|translate|escape:javascript}";
     var missingUsername = "{'Please, enter a login'|translate|escape:javascript}";

     var allUsers = [{$all_users}];
     var selection = [{$selection}];
     var pwg_token = "{$PWG_TOKEN}";

     var protectedUsers = [{$protected_users}];
     var guestUser = {$guest_user};

     var truefalse = {
	 'true':"{'Yes'|translate}",
	 'false':"{'No'|translate}",
     };

     var phyxo_msg = phyxo_msg || {};
     phyxo_msg.days = "{'%d days'|translate}";
     phyxo_msg.photos_per_page = "{'%d photos per page'|translate}";
     phyxo_msg.user_updated = "{'User %s updated'|translate}";
     phyxo_msg.are_you_sure = "{'Are you sure?'|translate}";

     phyxo_msg.open_user_details = "{'Open user details'|translate}";
     phyxo_msg.close_user_details = "{'Close user details'|translate}";
     phyxo_msg.edit = "{'edit'|translate}";
     phyxo_msg.translate = "{'close'|translate}";

     phyxo_msg.loading = "{'Loading...'|translate}";
     phyxo_msg.show_users = "{'Show %s users'|translate}";
     phyxo_msg.no_matching_user = "{'No matching user found'|translate}";
     phyxo_msg.showing_to_users = "{'Showing %s to %s of %s users'|translate}";
     phyxo_msg.filtered_from_total_users = "{'(filtered from %s total users)'|translate}";
     phyxo_msg.search = "{'Search'|translate}";
     phyxo_msg.first = "{'First'|translate}";
     phyxo_msg.previous = "{'Previous'|translate}";
     phyxo_msg.next = "{'Next'|translate}";
     phyxo_msg.last = "{'Last'|translate}";
     var statusLabels = {
	 {foreach from=$label_of_status key=status item=label}
	 '{$status}' : '{$label|escape:javascript}',
	 {/foreach}
     };
    </script>
    {/html_head}

    {combine_script id="user_list" load="footer" path="admin/theme/js/user_list.js"}

    <p>
	    <a href="#addUserForm" data-toggle="collapse" class="btn btn-submit"><i class="fa fa-plus-circle"></i> {'Add a user'|translate}</a>
	    <span class="infos" style="display:none"></span>
    </p>

    <div id="addUserForm" class="collapse">
    <form method="post" name="add_user" action="{$F_ADD_ACTION}">
	    <div class="fieldset">
	        <h3>{'Add a user'|translate}</h3>

            <p>
                <label>{'Username'|translate}
                    <input class="form-control" type="text" name="username" maxlength="50" size="20">
                </label>
            </p>

            <p>
                <label>{'Password'|translate}
                    <input class="form-control" type="{if $Double_Password}password{else}text{/if}" name="password">
                </label>
            </p>

            {if $Double_Password}
            <p>
                <label>{'Confirm Password'|translate}
                    <input class="form-control" type="password" name="password_confirm">
                </label>
            </p>
            {/if}

            <p>
                <label>{'Email address'|translate}
                    <input class="form-control" type="text" name="email">
                </label>
            </p>

            <p>
                <label><input type="checkbox" name="send_password_by_mail"> {'Send connection settings by email'|translate}</label>
            </p>

            <p>
                <input class="btn btn-submit" name="submit_add" type="submit" value="{'Submit'|translate}">
                <button class="btn btn-cancel" data-toggle="collapse">{'Cancel'|translate}</button>
                <span class="loading" style="display:none"><img src="./theme/images/ajax-loader-small.gif" alt=""></span>
                <span class="errors" style="display:none"></span>
            </p>
    	</div>
    </form>
    </div>

    <form method="post" name="preferences" action="">
        <table id="userList">
            <thead>
            <tr>
                <th>id</th>
                <th>{'Username'|translate}</th>
                <th>{'Status'|translate}</th>
                <th>{'Email address'|translate}</th>
                <th>{'Groups'|translate}</th>
                <th>{'Privacy level'|translate}</th>
                <th>{'registration date'|translate}</th>
            </tr>
            </thead>
        </table>

        <p class="checkActions">
            {'Select:'|translate}
            <a href="#" id="selectAll">{'All'|translate}</a>,
            <a href="#" id="selectNone">{'None'|translate}</a>,
            <a href="#" id="selectInvert">{'Invert'|translate}</a>

            <span id="selectedMessage"></span>
        </p>

        <div class="fieldset" id="action">
            <h3>{'Action'|translate}</h3>

            <div id="forbidAction"{if !empty($selection)} style="display:none"{/if}>{'No user selected, no action possible.'|translate}</div>
            <div id="permitAction"{if empty($selection)} style="display:none"{/if}>

            <select class="custom-select" name="selectAction">
                <option value="-1">{'Choose an action'|translate}</option>
                <option disabled="disabled">------------------</option>
                <option value="delete">{'Delete selected users'|translate}</option>
                <option value="status">{'Status'|translate}</option>
                <option value="group_associate">{'associate to group'|translate}</option>
                <option value="group_dissociate">{'dissociate from group'|translate}</option>
                <option value="enabled_high">{'High definition enabled'|translate}</option>
                <option value="level">{'Privacy level'|translate}</option>
                <option value="nb_image_page">{'Number of photos per page'|translate}</option>
                <option value="theme">{'Theme'|translate}</option>
                <option value="language">{'Language'|translate}</option>
                <option value="recent_period">{'Recent period'|translate}</option>
                <option value="expand">{'Expand all albums'|translate}</option>
                {if $ACTIVATE_COMMENTS}
                <option value="show_nb_comments">{'Show number of comments'|translate}</option>
                {/if}
                <option value="show_nb_hits">{'Show number of hits'|translate}</option>
            </select>

            {* delete *}
            <div id="action_delete" class="bulkAction">
                <p><label><input type="checkbox" name="confirm_deletion" value="1"> {'Are you sure?'|translate}</label></p>
            </div>

            {* status *}
            <div id="action_status" class="bulkAction">
                <select class="custom-select" name="status">
                {html_options options=$pref_status_options selected=$pref_status_selected}
                </select>
            </div>

            {* group_associate *}
            <div id="action_group_associate" class="bulkAction">
                {html_options name=associate options=$association_options selected=$associate_selected|default:null}
            </div>

            {* group_dissociate *}
            <div id="action_group_dissociate" class="bulkAction">
                {html_options name=dissociate options=$association_options selected=$dissociate_selected|default:null}
            </div>

            {* enabled_high *}
            <div id="action_enabled_high" class="bulkAction">
                <label><input type="radio" name="enabled_high" value="true">{'Yes'|translate}</label>
                <label><input type="radio" name="enabled_high" value="false" checked="checked">{'No'|translate}</label>
            </div>

            {* level *}
            <div id="action_level" class="bulkAction">
                <select class="custom-select" name="level" size="1">
                {html_options options=$level_options selected=$level_selected}
                </select>
            </div>

            {* nb_image_page *}
            <div id="action_nb_image_page" class="bulkAction">
                <strong class="nb_image_page_infos"></strong>
                <div class="nb_image_page"></div>
                <input type="hidden" name="nb_image_page" value="{$NB_IMAGE_PAGE}">
            </div>

            {* theme *}
            <div id="action_theme" class="bulkAction">
                <select class="custom-select" name="theme" size="1">
                {html_options options=$theme_options selected=$theme_selected}
                </select>
            </div>

            {* language *}
            <div id="action_language" class="bulkAction">
                <select class="custom-select" name="language" size="1">
                {html_options options=$language_options selected=$language_selected}
                </select>
            </div>

            {* recent_period *}
            <div id="action_recent_period" class="bulkAction">
                <div class="recent_period"></div>
                <span class="recent_period_infos"></span>
                <input type="hidden" name="recent_period" value="{$RECENT_PERIOD}">
            </div>

            {* expand *}
            <div id="action_expand" class="bulkAction">
                <label><input type="radio" name="expand" value="true">{'Yes'|translate}</label>
                <label><input type="radio" name="expand" value="false" checked="checked">{'No'|translate}</label>
            </div>

            {* show_nb_comments *}
            <div id="action_show_nb_comments" class="bulkAction">
                <label><input type="radio" name="show_nb_comments" value="true">{'Yes'|translate}</label>
                <label><input type="radio" name="show_nb_comments" value="false" checked="checked">{'No'|translate}</label>
            </div>

            {* show_nb_hits *}
            <div id="action_show_nb_hits" class="bulkAction">
                <label><input type="radio" name="show_nb_hits" value="true">{'Yes'|translate}</label>
                <label><input type="radio" name="show_nb_hits" value="false" checked="checked">{'No'|translate}</label>
            </div>

            <p id="applyActionBlock" style="display:none" class="actionButtons">
                <input id="applyAction" class="submit" type="submit" value="{'Apply action'|translate}" name="submit"> <span id="applyOnDetails"></span>
                <span id="applyActionLoading" style="display:none"><img src="./theme/images/ajax-loader-small.gif" alt=""></span>
                <span class="infos" style="display:none">&#x2714; {'Users modified'|translate}</span>
            </p>

            </div> {* #permitAction *}
        </div>
    </form>

    {* Underscore Template Definition *}
    <script type="text/template" class="userDetails">
     <form>
     <div class="fieldset">
     <div class="userActions">
     <% if (!user.isGuest) { %>
     <span class="changePasswordDone infos" style="display:none">&#x2714; {'Password updated'|translate}</span>
     <span class="changePassword" style="display:none">{'New password'|translate} <input type="text"> 
     <button class="btn btn-submit text">{'Submit'|translate}</button> <button class="btn btn-cancel cancel">{'Cancel'|translate}</button></span>
     <a class="changePasswordOpen" href="#"><i class="fa fa-key"></i> {'Change password'|translate}</a>
     <br>
     <% } %>

     <a href="./index.php?page=user_perm&amp;user_id=<%- user.id %>"><i class="fa fa-lock"></i> {'Permissions'|translate}</a>

     <% if (!user.isProtected) { %>
     <br><span class="userDelete"><img class="loading" src="./theme/images/ajax-loader-small.gif" alt="" style="display:none;"><a href="#" data-user_id="<%- user.id %>"><i class="fa fa-trash"></i>{'Delete'|translate}</a></span>
     <% } %>

     </div>

     <span class="changeUsernameOpen"><strong class="username"><%- user.username %></strong>

     <% if (!user.isGuest) { %>
	 <a href="#"><i class="fa fa-pencil"></i> {'Change username'|translate}</a></span>
     <span class="changeUsername" style="display:none">
     <input type="text"> 
     <button class="btn btn-submit text">{'Submit'|translate}</button> <button class="btn btn-cancel cancel">{'Cancel'|translate}</button>
     <% } %>

     </span>

     <div class="userStats"><%- user.registeredOn_string %><br><%- user.lastVisit_string %></div>

     <div class="userPropertiesContainer">
     <input type="hidden" name="user_id" value="<%- user.id %>">
     <div class="userPropertiesSet">
        <h3>{'Properties'|translate}</h3>

      <div class="userProperty">
        <label>{'Email address'|translate}
            <% if (!user.isGuest) { %>
                <input class="form-control" name="email" type="text" value="<%- user.email %>">
            <% } else { %>
            {'N/A'|translate}
            <% } %>
        </label>
      </div>

      <div class="userProperty">
        <label>{'Status'|translate}        
        <% if (!user.isProtected) { %>
            <select class="custom-select" name="status">
        <% _.each( user.statusOptions, function( option ){ %>
            <option value="<%- option.value%>" <% if (option.isSelected) { %>selected="selected"<% } %>><%- option.label %></option>
        <% }); %>
            </select>
        <% } else { %>
            <%- user.statusLabel %>
        <% } %>
        </label>
      </div>

      <div class="userProperty">
        <label>{'Privacy level'|translate}
            <select class="custom-select" name="level">
        <% _.each( user.levelOptions, function( option ){ %>
            <option value="<%- option.value%>" <% if (option.isSelected) { %>selected="selected"<% } %>><%- option.label %></option>
        <% }); %>
            </select>
        </label>
      </div>

      <div class="userProperty">
        <label>
            <input type="checkbox" name="enabled_high"<% if (user.enabled_high == 'true') { %> checked="checked"<% } %>> 
            {'High definition enabled'|translate}
        </label>
      </div>

      <div class="userProperty">
        <label>{'Groups'|translate}
            <select data-selectize="groups" placeholder="{'Type in a search term'|translate}"
            name="group_id[]" multiple></select>
        </label>
      </div>
     </div>

     <div class="userPropertiesSet userPrefs">
      <div class="userPropertiesSetTitle">{'Preferences'|translate}</div>

      <div class="userProperty">
        <strong class="nb_image_page_infos"></strong>
        <div class="nb_image_page"></div>
        <input type="hidden" name="nb_image_page" value="<%- user.nb_image_page %>">
      </div>

      <div class="userProperty">
        <label>{'Theme'|translate}
            <select class="custom-select" name="theme">
        <% _.each( user.themeOptions, function( option ){ %>
            <option value="<%- option.value%>" <% if (option.isSelected) { %>selected="selected"<% } %>><%- option.label %></option>
        <% }); %>
            </select>
        </label>
      </div>

      <div class="userProperty">
        <label>{'Language'|translate}
            <select class="custom-select" name="language">
        <% _.each( user.languageOptions, function( option ){ %>
            <option value="<%- option.value%>" <% if (option.isSelected) { %>selected="selected"<% } %>><%- option.label %></option>
        <% }); %>
            </select>
        </label>
      </div>

      <div class="userProperty">
        <label>{'Recent period'|translate}</label> <span class="recent_period_infos"></span>
        <div class="recent_period"></div>
        <input type="hidden" name="recent_period" value="<%- user.recent_period %>">
      </div>

      <div class="userProperty">
        <label>
            <input type="checkbox" name="expand"<% if (user.expand == 'true') { %> checked="checked"<% }%>> 
            {'Expand all albums'|translate}
        </label>
      </div>

      <div class="userProperty">
        <label>
            <input type="checkbox" name="show_nb_comments"<% if (user.show_nb_comments == 'true') { %> checked="checked"<% }%>> 
            {'Show number of comments'|translate}
        </label>
      </div>

      <div class="userProperty">
        <label>
            <input type="checkbox" name="show_nb_hits"<% if (user.show_nb_hits == 'true') { %> checked="checked"<% }%>> 
            {'Show number of hits'|translate}
        </label>
      </div>
     </div>

     <div style="clear:both"></div>
     </div> {* userPropertiesContainer *}

     <span class="infos propertiesUpdateDone" style="display:none">&#x2714; <%- user.updateString %></span>

     <input class="btn btn-submit" type="submit" value="{'Update user'|translate|escape:html}" style="display:none;" data-user_id="<%- user.id %>">
     <img class="submitWait" src="./theme/images/ajax-loader-small.gif" alt="" style="display:none">
     </div>
     </form>
    </script>
{/block}
