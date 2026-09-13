<details class="dc-connections-collection">
	<summary class="soft-button dc-connections-summary">
		<span class="dc-connections-title">{collection-title}</span>
		[has value="{category-slug}" comparison=""]
		<span class="dc-connections-category-slug">{category-slug}</span>
		[/has]
	</summary>
	<div class="dc-connections-body">
		[has value="{collection-description}" comparison=""]
		<p class="dc-connections-description">{collection-description}</p>
		[else]
		[/has]
		[has value="{collection-title}" comparison=""]
		<ul class="dc-connections-items">
			{items}
		</ul>
		[/has]
	</div>
</details>
