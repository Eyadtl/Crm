import './loader.css';

type LoaderProps = {
  message?: string;
};

export const Loader = ({ message = 'Loading...' }: LoaderProps) => (
  <div className="loader">
    <div className="spinner" />
    <p>{message}</p>
  </div>
);
