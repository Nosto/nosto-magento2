#!/usr/bin/env groovy

pipeline {

  environment {
    MAGENTO = credentials('sauce-lab-dev')
  }

  agent {
    dockerfile {
      additionalBuildArgs '--build-arg REPOUSER=${MAGENTO_USR} --build-arg REPOPASS=${MAGENTO_USR}'
    }
  }

  stages {
    stage('Checkout') {
      steps {
        checkout scm
      }
    }
    stage('Prebuild') {
      steps {
        script {
          sh 'echo hi'
        }
      }
    }
  }
}
