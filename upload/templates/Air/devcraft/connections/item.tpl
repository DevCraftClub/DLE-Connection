<li class="dc-connections-item">
	<a href="/index.php?newsid={news-id}" class="dc-connections-link">{title}</a>
	<span class="dc-connections-type">{relation-type}</span>
	[has value="{comment}"]
	<span class="dc-connections-comment">{comment}</span>
	[/has]
	[has value="{title}"]
	<span class="dc-connections-meta-title" hidden aria-hidden="true"></span>
	[/has]
	[has value="{date}"]
	<span class="dc-connections-date">{date}</span>
	[else]
	[/has]
</li>
