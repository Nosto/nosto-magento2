#!/usr/bin/env groovy

pipeline {

  environment {
    MAGENTO = credentials('magento')
  }

  agent {
    dockerfile {
      additionalBuildArgs '--build-arg REPOUSER=${MAGENTO_USR} --build-arg REPOPASS=${MAGENTO_USR}'
    }
  }

  stages {
    stage('"Prepare environment"') {
      steps {
        checkout scm
      }
    }
    stage('"Code Sniffer"') {
      steps {
        script {
          sh 'ls'
        }
        checkstyle 'phpcs.xml'
      }
    }
  }
}
