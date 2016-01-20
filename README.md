# behat-wordpress-context

This is a WordPress Context that adds a set of human-readable WordPress-related commands to the Behat default set. Testers can implement commands like creating and modifying WordPress default and custom posts, activating and customizing theme settings, adding images to the media library, creating and modifying user profiles, and more. 

It's useful for anyone who does highly customized WordPress implementations. The commands are configured to work with custom post types, and there are even commands for updating custom metabox data values within posts.

### Examples

#### Create a post of any type (post, page, or custom post type) and assign it a tile and slug for later referencing

```
Given I have a "post" item with the title "My First Post" and the slug "my-first-post"
```

#### Replace the taxonomy terms for an individual post item or clear them completely

```
Given the "post" item titled "My First Post" has the taxonomy "category" set to "uncategorized"
```

#### Create a new menu item within a given menu list in the WordPress database

```
Given I add a new menu item "Home" to the "Main" menu that links to "/home"
```

#### Create a new user or update an existing user in the WordPress database

```
Given the "contributor" user "test_username" with the password "test_password" exists
```

#### Set meta values within custom metaboxes for individual post items

```
Given the "post" item titled "My First Post" has the "sample" metabox with the "label" meta key set to "example"
```