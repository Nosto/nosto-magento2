#!/bin/bash

docker run --env IDEA_PROPERTIES=/app/idea.properties --env GITHUB_WORKSPACE=/app --volume=/Users/mridang/Junk/inspect/:/out --volume=$(pwd)/.:/app docker.pkg.github.com/mridang/action-phpstorm/stormy:latest /app /app/.idea/inspectionProfiles/CI.xml /tmp v2 Inspection
