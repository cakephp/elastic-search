# Global configuration information used across all the
# translations of documentation.
#
# Import the base theme configuration
from cakephpsphinx.config.all import *

# The version info for the project you're documenting, acts as replacement for
# |version| and |release|, also used in various other places throughout the
# built documents.
#

# The full version, including alpha/beta/rc tags.
release = '5.x'

# The search index version.
search_version = 'elasticsearch-5'

# The marketing display name for the book.
version_name = ''

# Project name shown in the black header bar
project = 'CakePHP ElasticSearch'

# Other versions that display in the version picker menu.
version_list = [
    {'name': '5.x', 'number': '/elasticsearch/5', 'title': '5.x', 'current': True},
    {'name': '4.x', 'number': '/elasticsearch/4', 'title': '4.x'},
    {'name': '3.x', 'number': '/elasticsearch/3', 'title': '3.x'},
    {'name': '2.x', 'number': '/elasticsearch/2', 'title': '2.x'},
]

# Languages available.
languages = ['en', 'ja']

# The GitHub branch name for this version of the docs
# for edit links to point at.
branch = '5.x'

# Current version being built
version = '5.x'

show_root_link = True

repository = 'cakephp/elastic-search'

source_path = 'docs/'

hide_page_contents = ('search', '404', 'contents')
