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
release = '3.x'

# The search index version.
search_version = 'elasticsearch-3'

# The marketing display name for the book.
version_name = ''

# Project name shown in the black header bar
project = 'CakePHP ElasticSearch'

# Other versions that display in the version picker menu.
version_list = [
    {'name': '3.x', 'number': '/elasticsearch/3', 'title': '3.x', 'current': True},
    {'name': '2.x', 'number': '/elasticsearch/2', 'title': '2.x'},
]

# Languages available.
languages = ['en', 'fr', 'ja', 'pt']

# The GitHub branch name for this version of the docs
# for edit links to point at.
branch = 'master'

# Current version being built
version = '3.x'

show_root_link = True

repository = 'cakephp/elastic-search'

source_path = 'docs/'

hide_page_contents = ('search', '404', 'contents')
