{template contentBlock Content contentClass=null extraClass=null accountLevelEdit=Staff}
    {$handle = tif(is_string($Content) ? $Content : $Content->Handle)}
    {$Content = tif(is_string($Content) ? Emergence\CMS\ContentBlock::getByHandle($Content) : $Content)}
    {$renderer = default($Content->Renderer, Emergence\CMS\ContentBlock::getFieldOptions('Renderer', 'default'))}

    <div class="content-{$renderer} {tif $.User->hasAccountLevel($accountLevelEdit) ? 'content-editable'} {tif $contentClass ? $contentClass : $handle} {$extraClass}" {if $.User->hasAccountLevel($accountLevelEdit)}data-content-endpoint="{Emergence\CMS\ContentBlock::$collectionRoute}" data-content-id="{$handle}" {if !$Content}data-content-phantom="true"{/if} data-content-field="Content" data-content-value="{$Content->Content|escape}" data-content-renderer="{$renderer}"{/if}>
        {tif $Content ? $Content->getHtml()}
    </div>
{/template}