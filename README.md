# Discovery

## Purpose

The Discovery extension can be used to fetch existing ads created by extension:Promoter.
Promoter campaigns correspond to categories; when the Discovery API is passed a page title, it
retrieves all the ads that belong to those campaigns/categories, and selects 4 of them.
If there aren't 4 ads, it fetches additional as from the fallback campaign.

It also provides its own widget, with a `<discovery>` tag, to display those.

## Configuration
All of the configuration is done under `$wgDiscoveryConfig`:

| Option             | Values     | Comments                                                                                                                                                                                                                                         |
|--------------------|------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| trackImpressions   | true/false |                                                                                                                                                                                                                                                  |
| trackClicks        | true/false |                                                                                                                                                                                                                                                  |
| blogUrl            | Url        | Allows recognizing promoter ads that lead to the blog                                                                                                                                                                                            |
| priorityCategories | null/[]    | An array of category names. If any of these categories are present on the page, ads will *only* be fetched from them. The priority is also determined by the order of the array, so that the first entry will take precedence over any following |

## How to use
You can do either of the following:
1. Use the tag `<discovery>` to get the component in the text
2. Add `<div class="discovery"></div>` in the desired location and add RL module `ext.discovery` yourself
3. Execute a GET request to `https://example.com/api.php?action=discovery&title=page_title&format=json` and create your own component/UI

If using the 1st or 2nd option, you can disable the component on a specific page by using the parser function `{{#disable_discovery:}}`

## Changelog
### 0.5.0, 2023-10-16
Add configuration for prioritizedCategories
### 0.4.0, 2023-04-03
Add parser function `{{#disable_discovery:}}`
### 0.3.0, 2018-12-12
Updated design to fit skin:Helena 4.0
### 0.2.1, 2018-10-09
Prevent duplicate ads

### 0.2.0, 2018-10-08
"See Also" removed completely from ads.
