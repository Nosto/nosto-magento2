#!/usr/bin/env groovy

pipeline {

  agent {
    label {
      label 'slave'
      customWorkspace "/var/lib/jenkins/workspace/${JOB_NAME}/${BUILD_NUMBER}"
    }
  }

  environment {
    REPO = credentials('magento')
    COMPOSE_FILE = 'docker-compose-ci.yml'
  }

  stages {
    stage('Prepare environment') {
      steps {
        checkout scm
        script {
          env['GIT_SHA'] = sh(returnStdout: true, script: 'git rev-parse HEAD').trim()
        }
      }
    }

    stage('Prebuild') {
      steps {
        script {
          if (!fileExists('console-logs')) {
            sh 'mkdir -m 755 console-logs'
          }
          sh(returnStatus: true, script: 'mkdir -m 755 slowtest-logs unittest-logs integrationtest-logs')
          sh "docker pull `cat Dockerfile | grep ^FROM | cut -d' ' -f2 | head -1`"
          env['COMPOSE_PROJECT_NAME_BUILD'] = "magento${env.BUILD_NUMBER}"
          env['COMPOSE_PROJECT_NAME_STATICTESTS'] = sh(returnStdout: true, script: 'echo statictests_${BUILD_NUMBER} | tr -d "[:punct:]" | tr "[:upper:]" "[:lower:]"').trim()
          sh 'echo ${GIT_SHA} > REVISION'
          sh 'echo ${COMPOSE_PROJECT_NAME_STATICTESTS}'
          sh 'docker-compose --version'
        }
      }
    }

    stage('Static Tests') {
      steps {
        parallel (
          'Preparation' : {
            script {
              sh 'export COMPOSE_PROJECT_NAME=${COMPOSE_PROJECT_NAME_STATICTESTS} && cat ${COMPOSE_FILE} | shyaml keys services | tail -n +2 | xargs docker-compose up -d'
              sh "#!/bin/bash \n" +
                   "set -o pipefail \n" +
                   "docker-compose -p ${COMPOSE_PROJECT_NAME_STATICTESTS} run -u root -T -w /var/www/html/community-edition/vendor/nosto/module-nostotagging magento composer config repositories.0 composer https://repo.magento.com \n" +
                   "docker-compose -p ${COMPOSE_PROJECT_NAME_STATICTESTS} run -u root -T -w /var/www/html/community-edition/vendor/nosto/module-nostotagging magento composer config http-basic.repo.magento.com $REPO_USR $REPO_PSW \n" +
                   "docker-compose -p ${COMPOSE_PROJECT_NAME_STATICTESTS} run -u root -T -w /var/www/html/community-edition/vendor/nosto/module-nostotagging magento composer install --no-progress --no-suggest \n" +
                   "docker-compose -p ${COMPOSE_PROJECT_NAME_STATICTESTS} run -u root -T -w /var/www/html/community-edition/vendor/nosto/module-nostotagging magento ./vendor/bin/phpcs --standard=ruleset.xml --report=checkstyle --report-file=chkphpcs.xml"
            }
          }
        )
      }
    }

    stage('Code Sniffer') {
      steps {
        catchError {
            sh "#!/bin/bash \n" +
              "set -o pipefail \n" +
              "docker-compose -p ${COMPOSE_PROJECT_NAME_STATICTESTS} run -u root -T -w /var/www/html/community-edition/vendor/nosto/module-nostotagging magento ./vendor/bin/phpcs --standard=ruleset.xml --report=checkstyle --report-file=chkphpcs.xml"
        }
      }
    }

  }

  post {
    always {
      checkstyle pattern: 'chk*.xml', unstableTotalAll:'0'
      pmd pattern: 'phpmd.xml', unstableTotalAll:'0'
      deleteDir()
    }
  }
}
