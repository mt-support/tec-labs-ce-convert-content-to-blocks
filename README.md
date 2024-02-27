## Community Events Extension: Convert Submitted Content to Blocks

This extension aims to convert the event content submitted through Community Events to block editor format.

**Note, this extension is still under development and NO ACTIVE SUPPORT is provided for it.**
If you would like to report a bug or a request, you can do it in the [Issues](https://github.com/mt-support/tec-labs-ce-convert-content-to-blocks/issues) section in the GitHub repository.

### Setting it up

Setting up the extension is simple, just install it and activate it on your WordPress site, alongside The Events Calendar and Community Events.

There are no settings for the extension at the moment.

### Requirements

The following two settings are required to be enabled for the extension to work.

* _Events > Settings > General > Activate Block Editor for Events_
* _Events > Settings > Community > Use visual editor for event descriptions_

If these settings are not enabled, the extension will not function.

### Filters

The plugin has two filters

#### `tec_labs_ce_block_template`

Allows changing the HTM block markup, including adding and removing elements.
```
/**
 * Remove the comments block.
 */
function my_custom_ce_blocks( $blocks, $data ) {
	// Remove the comments block.
	unset( $blocks['comments'] );

	return $blocks;
}
add_filter( 'tec_labs_ce_block_template', 'my_custom_ce_blocks', 10, 2 );

```

#### `tec_labs_ce_block_order`

Allows rearranging the order of the blocks based on the block slug.

Note: `content_start`, `content`, and `content_end` should stay together.

You can omit `content_start` and `content_end`. The plugin will make an attempt to include them.  

```
/**
 * Rearrange the event blocks.
 */
function my_custom_ce_block_order( $order ) {
	$order = [
		'featured_image',
		'organizer',
		'venue',
		'sharing',
		'related',
		'comments',
		'datetime',
		'content_start',
		'content',
		'content_end',
	];

	return $order;
}
add_filter( 'tec_labs_ce_block_order', 'my_custom_ce_block_order' );
```

### Important

The plugin will attempt the conversion for newly submitted events.  
It will not work for events submitted in the past. 

### Future Plans

* Add a setting for the cutoff date which is now hard-coded to be October 1, 2023.
