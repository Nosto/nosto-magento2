#!/usr/bin/env groovy

pipeline {

  agent none

  environment {
    REPO = credentials('magento')
  }

  stages {
    stage('Prepare environment') {
      agent { dockerfile true }
      steps {
        checkout scm
      }
    }

    stage('Update Dependencies') {
      agent { dockerfile true }
      steps {
        sh "composer config repositories.0 composer https://repo.magento.com"
        sh "composer config http-basic.repo.magento.com $REPO_USR $REPO_PSW"
        sh "composer install --no-progress --no-suggest"
      }
    }

    stage('Code Sniffer') {
      agent { dockerfile true }
      steps {
        catchError {
          sh "./vendor/bin/phpcs --standard=ruleset.xml --report=checkstyle --report-file=chkphpcs.xml || true"
        }
      }
    }

    stage('Mess Detection') {
      agent { dockerfile true }
      steps {
        catchError {
          sh "./vendor/bin/phpmd . xml codesize,naming,unusedcode,controversial,design --exclude vendor,var,build,tests --reportfile phpmd.xml || true"
        }
      }
    }

    stage('PhpStorm Inspections') {
      agent { docker { image 'supercid/phpstorminspections:latest' } }
      steps {
        sh "ls -lah"
        sh "ls -lah /home/plugins"
        sh "composer require shopsys/phpstorm-inspect"
        sh "ls -lah vendor/bin"
        script {
          try {
            sh "/home/plugins/PhpStorm-*/bin/inspect.sh || true" /* Initializes the IDE and the user preferences directory */
            sh "ls -lah /home/plugins/PhpStorm-*/bin"
          } catch (Exception e) {
            sh "curl -H 'Content-Type: application/json' --data ''{'build': true}'' -X POST https://registry.hub.docker.com/u/supercid/phpstorminspections/trigger/1b0eeeb8-c13a-4c87-81f8-2b0ac69f18ed/"
          } finally {
            sh "./vendor/bin/phpstorm-inspect /home/plugins/PhpStorm-*/bin/inspect.sh ~/.PhpStorm20*/system . .idea/inspectionProfiles/Project_Default.xml . text"
          }
        }
      }
    }

    stage('Phan Analysis') {
      agent { dockerfile true }
      steps {
        sh "composer create-project magento/community-edition magento"
        sh "cd magento && composer config minimum-stability dev"
        sh "cd magento && composer config prefer-stable true"
        script {
          try {
            sh "cd magento && composer require --update-no-dev nosto/module-nostotagging:dev-${CHANGE_BRANCH}#${env.GIT_COMMIT.substring(0, 7)}"
          } catch (MissingPropertyException e) {
            sh "cd magento && composer require --update-no-dev nosto/module-nostotagging:dev-${env.GIT_BRANCH}#${env.GIT_COMMIT.substring(0, 7)}"
          }
        }
        sh "cd magento && bin/magento module:enable --all"
        sh "cd magento && bin/magento setup:di:compile"
        catchError {
          sh "./vendor/bin/phan --config-file=phan.php --output-mode=checkstyle --output=chkphan.xml || true"
        }
      }
    }

    stage('Package') {
      agent { dockerfile true }
      steps {
        script {
          version = sh(returnStdout: true, script: 'git rev-parse --short HEAD').trim()
          sh "composer archive --format=zip --file=${version}"
          sh "composer validate-archive -- ${version}.zip"
        }
        archiveArtifacts "${version}.zip"
      }
    }
  }

  post {
    always {
       node('master') {
        checkstyle pattern: 'chk*.xml', unstableTotalAll:'0'
        pmd pattern: 'phpmd.xml', unstableTotalAll:'0'
        deleteDir()
      }
    }
  }
}
