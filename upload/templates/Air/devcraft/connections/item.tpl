<li class="dc-connections-item">
	<div class="dc-connections-item__body">
		<a href="{full-link}" class="dc-connections-link">{title}</a>
		<div class="dc-connections-item__meta">
			[has value="{relation-type}"]
			<span class="chips chips--rub">{relation-type}</span>
			[/has]
			[has value="{date-iso}"]
			<time class="meta-item" datetime="{date-iso}"><svg class="icon" aria-hidden="true"><use href="#i-clock"></use></svg>{date}</time>
			[/has]
		</div>
		[has value="{comment}"]
		<p class="dc-connections-comment">{comment}</p>
		[/has]
	</div>
</li>
