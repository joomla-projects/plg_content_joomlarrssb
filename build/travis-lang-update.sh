#!/usr/bin/env bash
if [[ $TRAVIS_PULL_REQUEST == "false" && $TRAVIS_BRANCH == "master" ]]; then
  cd ../

  echo -e "Starting translation update\n"

  #download the Crowdin CLI app and update the sources
  wget https://crowdin.com/downloads/crowdin-cli.jar
  java -jar crowdin-cli.jar upload sources
  rm crowdin-cli.jar

  echo -e "en-GB language sources synchronized\n"
fi
