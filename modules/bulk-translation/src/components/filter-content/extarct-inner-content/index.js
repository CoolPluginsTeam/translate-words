import ReactDOM from 'react-dom';

const extractInnerContent = async (element) => {
    const container = document.createElement('div');
    const root = ReactDOM.createRoot(container);

    try {
        // Read the markup only after React has committed the rendered text.
        ReactDOM.flushSync(() => root.render(element));
        return container.innerHTML;
    } finally {
        root.unmount();
    }
};

export default extractInnerContent;
