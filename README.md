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

### Important

The plugin will attempt the conversion for newly submitted events.  
It will not work for events submitted in the past. 

### Future Plans

* Add a setting for the cutoff date which is now hard-coded to be October 1, 2023.
* Adding a filter to be able to adjust the order of the blocks.