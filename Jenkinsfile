#!/usr/bin/env groovy

pipeline {

  environment {
    MAGENTO = credentials('magento')
    FOO = 'moomoo'
  }

  agent any
  stages {
    stage('Prepare environment') {
      steps {
        echo "${MAGENTO}"
        echo "${env.MAGENTO}"
        echo "${env.MAGENTO_USR}"
        echo "${env.FOO}"
      }
    }
  }
}
