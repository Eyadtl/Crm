import { useQuery } from '@tanstack/react-query';
import { fetchProjects } from '../api/projects';
import { Loader } from '../components/feedback/Loader';

const ProjectsPage = () => {
  const { data, isLoading } = useQuery({
    queryKey: ['projects'],
    queryFn: () => fetchProjects({ per_page: 20 }),
  });

  if (isLoading || !data) {
    return <Loader message="Loading projects..." />;
  }

  return (
    <div className="page">
      <div className="page__header">
        <h2>Projects</h2>
      </div>
      <div className="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Status</th>
              <th>Expected Close</th>
            </tr>
          </thead>
          <tbody>
            {data.data.map((project) => (
              <tr key={project.id}>
                <td>{project.deal_name}</td>
                <td>{project.deal_status?.name ?? 'Unassigned'}</td>
                <td>{project.expected_close_date ?? 'N/A'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};

export default ProjectsPage;
