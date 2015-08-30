#!/bin/sh
cd ..
rm -rf packaging && rm joomlarrssb.zip && mkdir packaging
cp -r language/ packaging/language/
cp -r media/ packaging/media/
cp -r tmpl/ packaging/tmpl/
cp -r *.php packaging/
cp -r joomlarrssb.xml packaging/
cd packaging/
zip -r ../joomlarrssb.zip *
