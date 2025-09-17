/*
 * Telemedi FX Desk - Main Application Entry Point
 * 
 * TypeScript + React application for currency exchange rate management.
 * Provides dashboard and historical views for FX desk operations.
 */

import React from 'react';
import ReactDOM from 'react-dom';
import { BrowserRouter as Router } from 'react-router-dom';
import '../css/app.css';
import Home from './components/Home.tsx';

// Render the main application (React 17 syntax)
ReactDOM.render(
  <React.StrictMode>
    <Router>
      <Home />
    </Router>
  </React.StrictMode>,
  document.getElementById('root')
);
