import { render } from '@wordpress/element';
import DataCenter from './components/DataCenter';
import './style.css';

// Render the Data Center component
const rootElement = document.getElementById('localseo-data-center');

if (rootElement) {
    render(<DataCenter />, rootElement);
}
