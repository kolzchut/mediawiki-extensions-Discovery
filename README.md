# Discovery

## Purpose

The purpose of the Discovery extension is -
- to fetch existing ads from the database, according to the current page category names (that correspond to existing ad campaign names)

And eventually return the results as a JSON object.

## Configuration

- $ads - The final array of ads to be returned from the API

- $excludedUrls - An array of URLs that's being updated every time an Ad url is being fetched from the database. Used to exclude existing ad URLs from database results

- MAX_AD_ITEMS - Maximum number of Ad items to fetch from the database

## How to use

1. Use the tag `<discovery>` to get the component in the text
2. Add `<div class="discovery"></div>` in the desired location and add RL module `ext.discovery` yourself
3. Execute a GET request to `https://example.com/api.php?action=discovery&title=page_title&format=json` and create your own component/UI

To disable the component on a specific page, use parser function `{{#disable_discovery:}}`

## Changelog
### 0.4.0, 2023-04-03
Add parser function `{{#disable_discovery:}}`
### 0.3.0, 2018-12-12
Updated design to fit skin:Helena 4.0
### 0.2.1, 2018-10-09
Prevent duplicate ads

### 0.2.0, 2018-10-08
"See Also" removed completely from ads.
