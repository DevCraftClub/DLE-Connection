<details class="dc-connections-collection">
	<summary class="dc-connections-summary">
		<span class="dc-connections-title">{collection-title}</span>
		[has value="{category-slug}"]
		<span class="dc-connections-category-slug">{category-slug}</span>
		[/has]
	</summary>
	<div class="dc-connections-body">
		[has value="{collection-description}"]
		<p class="dc-connections-description">{collection-description}</p>
		[else]
		[/has]
		[has value="{collection-title}"]
		<ul class="dc-connections-items">
			{items}
		</ul>
		[/has]
	</div>
</details>
