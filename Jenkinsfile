#!/usr/bin/env groovy

pipeline {

  agent {
    dockerfile {
      { additionalBuildArgs '--build-arg REPOUSER=foo REPOPASS=bar' }
    }
  }

  stages {
    stage('Checkout') {
      steps {
        checkout scm
      }
    }
    stage('Prebuild') {
      script {
        sh 'echo hi'
      }
    }
  }
}
