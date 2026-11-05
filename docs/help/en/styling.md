# Adapt the look

Reservations takes the font and link colour of your template and brings its own styles for everything else. You change colours and spacing through CSS variables in the stylesheet of the template.

## Set variables

Add the variables to the CSS of your template, on `.mod_reservations`:

```css
.mod_reservations {
    --rsv-line: #999;
    --rsv-weekend: #eef3f8;
    --rsv-week-row: 1.6em;
}
```

> [!WARNING]
> The variables have no effect on `:root` or `body`. They must be on `.mod_reservations`.

## The variables

| Variable | Default | Effect |
|---|---|---|
| `--rsv-line` | `#c8ccd2` | lines of tables and lists, borders of the legend |
| `--rsv-surface` | `#fff` | ground of the status patterns and of labels on them |
| `--rsv-ink` | `#1f2933` | text on this ground |
| `--rsv-weekend` | `#f1f3f5` | background of weekends |
| `--rsv-closed` | `#d6dae0` | hatching of unavailable times |
| `--rsv-closed-ground` | `#f6f7f9` | ground of this hatching |
| `--rsv-error` | `#a32d2d` | error messages in the form |
| `--rsv-success` | `#1e6b34` | confirmations in the form |
| `--rsv-radius` | `4px` | corners of the view buttons |
| `--rsv-space` | `1rem` | space below tables, groups and messages |
| `--rsv-plan-row` | `1.8em` | row height in the occupancy plan |
| `--rsv-month-row` | `2.8em` | row height in the calendar |
| `--rsv-week-row` | `1.3em` | row height in the week |

For dark templates, set at least `--rsv-surface`, `--rsv-ink`, `--rsv-weekend` and the two variables for closed times.

## Colours of the statuses

You do not set the colours of the statuses in the CSS, but in the backend under [Statuses](statuses.md).

## When the template interferes

The module resets the template's styles for its tables and lists, and gives its own rules a high priority. This keeps the calendar unchanged in most templates.

Only template rules with `!important` prevail against that. If a calendar still does not look right, search the template's stylesheet for such rules on `table`, `td` or `ul`.

> [!NOTE]
> Form fields and buttons keep the look of the template. The captcha brings its own styles and stays untouched.
