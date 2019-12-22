{extends file="mail/text/__layout.text.tpl"}

{block name="content"}
    {'Someone requested that the password be reset for the following user account:'|translate}
    {'Username "{username}" on gallery {url}'|translate:['username' => $user['username'], 'url' => $gallery_url]}

    {'To reset your password, visit the following address:'|translate} {$url}

    {'If this was a mistake, just ignore this email and nothing will happen.'|translate}
{/block}
