<template>
    <v-container>
      <v-row justify="center">
        <v-col cols="12" sm="8" md="6" lg="4">
          <v-card>
            <v-card-title class="headline">Login</v-card-title>
            <v-card-text>
              <v-text-field label="Username" v-model="username"></v-text-field>
              <v-text-field label="Password" type="password" v-model="password"></v-text-field>
              <v-btn color="primary" @click="login">Login</v-btn>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </template>
  
  <script>
  export default {
    data() {
      return {
        username: '',
        password: ''
      };
    },
    methods: {
      login() {
        // Логика авторизации
        console.log('Logging in...');
      }
    }
  };
  </script>