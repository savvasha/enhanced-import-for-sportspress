=== Enhanced Import for SportsPress ===
Contributors: savvasha
Tags: fixtures, players, import, scores, metrics
Requires at least: 5.3
Tested up to: 7.0
Stable tag: 2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl.html

Extends SportsPress CSV importers with score/results support and enhanced functionality for importing fixtures with match outcomes.

== Description ==

**Enhanced Import for SportsPress** extends the default SportsPress CSV importers with advanced features, specifically designed to import fixtures with scores and results data.

### What This Plugin Does

This plugin enhances the standard SportsPress Fixtures (CSV) and Players (CSV) importers.

For Fixtures it adds support for:

* **Score/Results Import**: Import match scores directly from CSV files
* **Automatic Results Processing**: Automatically creates and updates match results using SportsPress's native results system
* **Auto creation of Calendar and League table**: Teams are auto assigned to League Table.
* **Backward Compatibility**: Works with existing SportsPress CSV formats while adding new capabilities

For Players it adds support for:

* **Player ID column (update by ID)**: When a Player ID is provided, the matching player is updated; otherwise a new player is created. Without a Player ID it behaves like the native importer (using the Merge duplicates option).
* **Metric columns**: All registered metrics (sp_metric) are available as columns, so metric values can be imported into players. Blank metric cells preserve existing values on updates.

### Key Features

* **Score Support**: Import home and away scores directly from CSV
* **Outcome calculation**: Use native SportsPress functionality to calculate the outcome of an event.
* **Auto create League Table and Calendar**: Gives the ability to the user to automacally create a new League Table or/and a new Calendar.
* **Teams assignment to League Table**: Handles teams assignment to League Table to avoid false positives.

### Import Fixtures: CSV Format

The enhanced importer for Fixtures adds the following CSV columns:

1. **Home Score** – Home team score (optional)
2. **Away Score** – Away team score (optional)

#### Example Fixtures CSV

    Date,Time,Venue,Home,Away,Home Score,Away Score,Match Day
    2024/01/15,19:30,Stadium A,Team Alpha,Team Beta,2,1,GW1
    2024/01/20,20:00,Stadium B,Team Gamma,Team Delta,0,3,GW2
    2024/01/25,18:45,Stadium A,Team Beta,Team Gamma,1,1,GW2

---

### Import Players: CSV Format

The enhanced importer for Players supports these additional columns:

1. **Player ID** – If provided, updates the existing player by ID; if not, a new player is created. (optional)
2. **Metric Columns (sp_metric)** – All registered SportsPress metrics will appear as columns; you may import metric values for each player. Any blank metric cell will keep the player’s existing value for that metric.

#### Example Players CSV

    Player ID,First Name,Last Name,Team,Goals,Assists,Yellow Cards
    1234,John,Doe,Team Alpha,2,1,0
    ,Jane,Smith,Team Beta,0,0,1
    1236,Tom,Johnson,Team Gamma,1,,2


== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/enhanced-import-for-sportspress/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Ensure SportsPress plugin is installed and activated.
4. Go to **Tools > Import** and select **"Import Fixtures (CSV) Enhanced"** or **"Import Players (CSV) Enhanced"**.

== Frequently Asked Questions ==

= Does this plugin require SportsPress? =

Yes, this plugin requires SportsPress to be installed and activated. It extends the existing SportsPress import functionality.

= Can I use this with existing CSV files? =

Yes! The plugin is backward compatible with existing SportsPress CSV formats. The score columns are optional, so your existing CSV files will work without modification.

= What happens if teams don't exist? =

The plugin automatically creates teams if they don't exist in your SportsPress installation. Teams are created with the same name as specified in the CSV.

= How are scores processed? =

Scores are processed using SportsPress's native functions, ensuring proper integration with the SportsPress results system.

= Can I import partial data? =

Yes, you can import CSV files with missing optional columns. Only Date, Home team, and Away team are required.

== Screenshots ==

1. You can use the native Import button at SportsPress->Events area
2. Or you use the Import tool directly from Tools->Import.
3. Enhanced import interface with score columns and option for auto creation of league table and calendar.

== Changelog ==

= 2.0 =
* Added an enhanced Players (CSV) importer with a Player ID column (update existing players by ID) and dynamic metric columns.

= 1.0 =
* Initial release

== Upgrade Notice ==

= 2.0 =
Adds an enhanced Players (CSV) importer with update-by-ID and metric column support.

= 1.0 =
Initial release of Enhanced Import for SportsPress with score support and enhanced functionality.

== Credits ==

* Built for SportsPress by [Savvas](https://savvasha.com/)
* Based on the original SportsPress importers by [ThemeBoy](https://www.themeboy.com/)
* Licensed under GPLv2 or later
