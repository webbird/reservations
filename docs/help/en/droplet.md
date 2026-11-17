# Calendar in a post (droplet)

With the droplet `[[reservations]]` you show a calendar anywhere, for example in the news_img post or article of an object. The droplet is installed with the module.

## Notations

| Droplet | Shows |
|---|---|
| `[[reservations]]` | in a news_img post or article: the calendar of the object it is [linked](descriptions.md) to |
| `[[reservations?resource=ID]]` | one object as a month calendar, on any page |
| `[[reservations?section=ID]]` | a whole section, as set up there |

You find the ID of an object in the address when editing it, after `rsv_edit=`. The ID of the section is there after `rsv_section=`.

## If nothing appears

If nothing fits, the output stays empty: the object is inactive or deleted, the post is not linked to an object, or the ID is wrong. Logged-in administrators find the reason as an HTML comment in the page source.

> [!NOTE]
> If the same section appears twice on a page, for example as a section and as a droplet, paging affects both.
