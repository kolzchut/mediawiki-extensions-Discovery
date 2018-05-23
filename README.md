# Discovery

## Purpose

The purpose of the Discovery extension is -
- to fetch existing ads from the database, according to the current page category names (that correspond to existing ad campaign names)
- to fetch existing 'See Also' ("ראו גם") items from the current page using the Semantic Mediawiki API

And eventually return the results as a JSON object.

## Configuration

- $ads - The final array of ads to be returned from the API

- $seeAlso - The final array of 'See also' items to be returned from the API

- $urls - An array of URLs that's being updated every time a See Also url/Ad url is being fetched from the database. Used to exclude existing ad URLs from database results

- MAX_AD_ITEMS - Maximum number of Ad items to fetch from the database

- MAX_SEE_ALSO_ITEMS - Maximum number of See Also items to fetch from the database

## How to use

Simply execute a GET request to http://example.com/api.php?action=discovery&title={page title}&format=json