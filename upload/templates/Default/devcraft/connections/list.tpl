<nav class="dc-connections-collection" aria-labelledby="dc-conn-{collection-id}">
	<h2 class="dc-connections-title" id="dc-conn-{collection-id}">{collection-title}</h2>
	[has value="{category-slug}"]
	<p class="dc-connections-category-slug">{category-slug}</p>
	[/has]
	<div class="dc-connections-body">
		[has value="{collection-description}"]
		<p class="dc-connections-description">{collection-description}</p>
		[/has]
		<ul class="dc-connections-items">
			{items}
		</ul>
	</div>
</nav>
