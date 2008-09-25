;(function($){var $cluetip,$cluetipInner,$cluetipOuter,$cluetipTitle,$cluetipArrows,$dropShadow,imgCount;$.fn.cluetip=function(js,options){if(typeof js=='object'){options=js;js=null}return this.each(function(index){var $this=$(this);var opts=$.extend(false,{},$.fn.cluetip.defaults,options||{},$.metadata?$this.metadata():$.meta?$this.data():{});var cluetipContents=false;var cluezIndex=parseInt(opts.cluezIndex,10)-1;var isActive=false,closeOnDelay=0;if(!$('#cluetip').length){$cluetipInner=$('<div id="cluetip-inner"></div>');$cluetipTitle=$('<h3 id="cluetip-title"></h3>');$cluetipOuter=$('<div id="cluetip-outer"></div>').append($cluetipInner).prepend($cluetipTitle);$cluetip=$('<div id="cluetip"></div>').css({zIndex:opts.cluezIndex}).append($cluetipOuter).append('<div id="cluetip-extra"></div>')[insertionType](insertionElement).hide();$('<div id="cluetip-waitimage"></div>').css({position:'absolute',zIndex:cluezIndex-1}).insertBefore('#cluetip').hide();$cluetip.css({position:'absolute',zIndex:cluezIndex});$cluetipOuter.css({position:'relative',zIndex:cluezIndex+1});$cluetipArrows=$('<div id="cluetip-arrows" class="cluetip-arrows"></div>').css({zIndex:cluezIndex+1}).appendTo('#cluetip')}var dropShadowSteps=(opts.dropShadow)?+opts.dropShadowSteps:0;if(!$dropShadow){$dropShadow=$([]);for(var i=0;i<dropShadowSteps;i++){$dropShadow=$dropShadow.add($('<div></div>').css({zIndex:cluezIndex-i-1,opacity:.1,top:1+i,left:1+i}))};$dropShadow.css({position:'absolute',backgroundColor:'#000'}).prependTo($cluetip)}var tipAttribute=$this.attr(opts.attribute),ctClass=opts.cluetipClass;if(!tipAttribute&&!opts.splitTitle&&!js)return true;if(opts.local&&opts.hideLocal){$(tipAttribute+':first').hide()}var tOffset=parseInt(opts.topOffset,10),lOffset=parseInt(opts.leftOffset,10);var tipHeight,wHeight;var defHeight=isNaN(parseInt(opts.height,10))?'auto':(/\D/g).test(opts.height)?opts.height:opts.height+'px';var sTop,linkTop,posY,tipY,mouseY,baseline;var tipInnerWidth=isNaN(parseInt(opts.width,10))?275:parseInt(opts.width,10);var tipWidth=tipInnerWidth+(parseInt($cluetip.css('paddingLeft'))||0)+(parseInt($cluetip.css('paddingRight'))||0)+dropShadowSteps;var linkWidth=this.offsetWidth;var linkLeft,posX,tipX,mouseX,winWidth;var tipParts;var tipTitle=(opts.attribute!='title')?$this.attr(opts.titleAttribute):'';if(opts.splitTitle){if(tipTitle==undefined){tipTitle=''}tipParts=tipTitle.split(opts.splitTitle);tipTitle=tipParts.shift()}var localContent;var activate=function(event){if(!opts.onActivate($this)){return false}isActive=true;$cluetip.removeClass().css({width:tipInnerWidth});if(tipAttribute==$this.attr('href')){$this.css('cursor',opts.cursor)}$this.attr('title','');if(opts.hoverClass){$this.addClass(opts.hoverClass)}linkTop=posY=$this.offset().top;linkLeft=$this.offset().left;mouseX=event.pageX;mouseY=event.pageY;if($this[0].tagName.toLowerCase()!='area'){sTop=$(document).scrollTop();winWidth=$(window).width()}if(opts.positionBy=='fixed'){posX=linkWidth+linkLeft+lOffset;$cluetip.css({left:posX})}else{posX=(linkWidth>linkLeft&&linkLeft>tipWidth)||linkLeft+linkWidth+tipWidth+lOffset>winWidth?linkLeft-tipWidth-lOffset:linkWidth+linkLeft+lOffset;if($this[0].tagName.toLowerCase()=='area'||opts.positionBy=='mouse'||linkWidth+tipWidth>winWidth){if(mouseX+20+tipWidth>winWidth){$cluetip.addClass(' cluetip-'+ctClass);posX=(mouseX-tipWidth-lOffset)>=0?mouseX-tipWidth-lOffset-parseInt($cluetip.css('marginLeft'),10)+parseInt($cluetipInner.css('marginRight'),10):mouseX-(tipWidth/2)}else{posX=mouseX+lOffset}}var pY=posX<0?event.pageY+tOffset:event.pageY;$cluetip.css({left:(posX>0&&opts.positionBy!='bottomTop')?posX:(mouseX+(tipWidth/2)>winWidth)?winWidth/2-tipWidth/2:Math.max(mouseX-(tipWidth/2),0)})}wHeight=$(window).height();if(js){$cluetipInner.html(js);cluetipShow(pY)}else if(tipParts){var tpl=tipParts.length;for(var i=0;i<tpl;i++){if(i==0){$cluetipInner.html(tipParts[i])}else{$cluetipInner.append('<div class="split-body">'+tipParts[i]+'</div>')}};cluetipShow(pY)}else if(!opts.local&&tipAttribute.indexOf('#')!=0){if(cluetipContents&&opts.ajaxCache){$cluetipInner.html(cluetipContents);cluetipShow(pY)}else{var ajaxSettings=opts.ajaxSettings;ajaxSettings.url=tipAttribute;ajaxSettings.beforeSend=function(){$cluetipOuter.children().empty();if(opts.waitImage){$('#cluetip-waitimage').css({top:mouseY+20,left:mouseX+20}).show()}};ajaxSettings.error=function(){if(isActive){$cluetipInner.html('<i>sorry, the contents could not be loaded</i>')}};ajaxSettings.success=function(data){cluetipContents=opts.ajaxProcess(data);if(isActive){$cluetipInner.html(cluetipContents)}};ajaxSettings.complete=function(){imgCount=$('#cluetip-inner img').length;if(imgCount&&!$.browser.opera){$('#cluetip-inner img').load(function(){imgCount--;if(imgCount<1){$('#cluetip-waitimage').hide();if(isActive)cluetipShow(pY)}})}else{$('#cluetip-waitimage').hide();if(isActive)cluetipShow(pY)}};$.ajax(ajaxSettings)}}else if(opts.local){var $localContent=$(tipAttribute+':first');var localCluetip=$.fn.wrapInner?$localContent.wrapInner('<div></div>').children().clone(true):$localContent.html();$.fn.wrapInner?$cluetipInner.empty().append(localCluetip):$cluetipInner.html(localCluetip);cluetipShow(pY)}};var cluetipShow=function(bpY){$cluetip.addClass('cluetip-'+ctClass);if(opts.truncate){var $truncloaded=$cluetipInner.text().slice(0,opts.truncate)+'...';$cluetipInner.html($truncloaded)}function doNothing(){};tipTitle?$cluetipTitle.show().html(tipTitle):(opts.showTitle)?$cluetipTitle.show().html('&nbsp;'):$cluetipTitle.hide();if(opts.sticky){var $closeLink=$('<div id="cluetip-close"><a href="#">'+opts.closeText+'</a></div>');(opts.closePosition=='bottom')?$closeLink.appendTo($cluetipInner):(opts.closePosition=='title')?$closeLink.prependTo($cluetipTitle):$closeLink.prependTo($cluetipInner);$closeLink.click(function(){cluetipClose();return false});if(opts.mouseOutClose){if($.fn.hoverIntent&&opts.hoverIntent){$cluetip.hoverIntent({over:doNothing,timeout:opts.hoverIntent.timeout,out:function(){$closeLink.trigger('click')}})}else{$cluetip.hover(doNothing,function(){$closeLink.trigger('click')})}}else{$cluetip.unbind('mouseout')}}var direction='';$cluetipOuter.css({overflow:defHeight=='auto'?'visible':'auto',height:defHeight});tipHeight=defHeight=='auto'?Math.max($cluetip.outerHeight(),$cluetip.height()):parseInt(defHeight,10);tipY=posY;baseline=sTop+wHeight;if(opts.positionBy=='fixed'){tipY=posY-opts.dropShadowSteps+tOffset}else if((posX<mouseX&&Math.max(posX,0)+tipWidth>mouseX)||opts.positionBy=='bottomTop'){if(posY+tipHeight+tOffset>baseline&&mouseY-sTop>tipHeight+tOffset){tipY=mouseY-tipHeight-tOffset;direction='top'}else{tipY=mouseY+tOffset;direction='bottom'}}else if(posY+tipHeight+tOffset>baseline){tipY=(tipHeight>=wHeight)?sTop:baseline-tipHeight-tOffset}else if($this.css('display')=='block'||$this[0].tagName.toLowerCase()=='area'||opts.positionBy=="mouse"){tipY=bpY-tOffset}else{tipY=posY-opts.dropShadowSteps}if(direction==''){posX<linkLeft?direction='left':direction='right'}$cluetip.css({top:tipY+'px'}).removeClass().addClass('clue-'+direction+'-'+ctClass).addClass(' cluetip-'+ctClass);if(opts.arrows){var bgY=(posY-tipY-opts.dropShadowSteps);$cluetipArrows.css({top:(/(left|right)/.test(direction)&&posX>=0&&bgY>0)?bgY+'px':/(left|right)/.test(direction)?0:''}).show()}else{$cluetipArrows.hide()}$dropShadow.hide();$cluetip.hide()[opts.fx.open](opts.fx.open!='show'&&opts.fx.openSpeed);if(opts.dropShadow)$dropShadow.css({height:tipHeight,width:tipInnerWidth}).show();if($.fn.bgiframe){$cluetip.bgiframe()}if(opts.delayedClose>0){closeOnDelay=setTimeout(cluetipClose,opts.delayedClose)}opts.onShow($cluetip,$cluetipInner)};var inactivate=function(){isActive=false;$('#cluetip-waitimage').hide();if(!opts.sticky||(/click|toggle/).test(opts.activation)){cluetipClose();clearTimeout(closeOnDelay)};if(opts.hoverClass){$this.removeClass(opts.hoverClass)}$('.cluetip-clicked').removeClass('cluetip-clicked')};var cluetipClose=function(){$cluetipOuter.parent().hide().removeClass().end().children().empty();if(tipTitle){$this.attr(opts.titleAttribute,tipTitle)}$this.css('cursor','');if(opts.arrows)$cluetipArrows.css({top:''})};if((/click|toggle/).test(opts.activation)){$this.click(function(event){if($cluetip.is(':hidden')||!$this.is('.cluetip-clicked')){activate(event);$('.cluetip-clicked').removeClass('cluetip-clicked');$this.addClass('cluetip-clicked')}else{inactivate(event)}this.blur();return false})}else if(opts.activation=='focus'){$this.focus(function(event){activate(event)});$this.blur(function(event){inactivate(event)})}else{$this.click(function(){if($this.attr('href')&&$this.attr('href')==tipAttribute&&!opts.clickThrough){return false}});var mouseTracks=function(evt){if(opts.tracking==true){var trackX=posX-evt.pageX;var trackY=tipY?tipY-evt.pageY:posY-evt.pageY;$this.mousemove(function(evt){$cluetip.css({left:evt.pageX+trackX,top:evt.pageY+trackY})})}};if($.fn.hoverIntent&&opts.hoverIntent){$this.mouseover(function(){$this.attr('title','')}).hoverIntent({sensitivity:opts.hoverIntent.sensitivity,interval:opts.hoverIntent.interval,over:function(event){activate(event);mouseTracks(event)},timeout:opts.hoverIntent.timeout,out:function(event){inactivate(event);$this.unbind('mousemove')}})}else{$this.hover(function(event){activate(event);mouseTracks(event)},function(event){inactivate(event);$this.unbind('mousemove')})}}})};$.fn.cluetip.defaults={width:275,height:'auto',cluezIndex:97,positionBy:'auto',topOffset:15,leftOffset:15,local:false,hideLocal:true,attribute:'rel',titleAttribute:'title',splitTitle:'',showTitle:true,cluetipClass:'default',hoverClass:'',waitImage:true,cursor:'help',arrows:false,dropShadow:true,dropShadowSteps:6,sticky:false,mouseOutClose:false,activation:'hover',clickThrough:false,tracking:false,delayedClose:0,closePosition:'top',closeText:'Close',truncate:0,fx:{open:'show',openSpeed:''},hoverIntent:{sensitivity:3,interval:50,timeout:0},onActivate:function(e){return true},onShow:function(ct,c){},ajaxCache:true,ajaxProcess:function(data){data=data.replace(/<s(cript|tyle)(.|\s)*?\/s(cript|tyle)>/g,'').replace(/<(link|title)(.|\s)*?\/(link|title)>/g,'');return data},ajaxSettings:{dataType:'html'},debug:false};var insertionType='appendTo',insertionElement='body';$.cluetip={};$.cluetip.setup=function(options){if(options&&options.insertionType&&(options.insertionType).match(/appendTo|prependTo|insertBefore|insertAfter/)){insertionType=options.insertionType}if(options&&options.insertionElement){insertionElement=options.insertionElement}}})(jQuery);