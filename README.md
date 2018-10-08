# Discovery

## Purpose

The purpose of the Discovery extension is -
- to fetch existing ads from the database, according to the current page category names (that correspond to existing ad campaign names)

And eventually return the results as a JSON object.

## Configuration

- $ads - The final array of ads to be returned from the API

- $urls - An array of URLs that's being updated every time an Ad url is being fetched from the database. Used to exclude existing ad URLs from database results

- MAX_AD_ITEMS - Maximum number of Ad items to fetch from the database

## How to use

Simply execute a GET request to http://example.com/api.php?action=discovery&title=page_title&format=json

## Changelog
### 0.2.0, 2018-10-08
"See Also" removed completely from ads.
